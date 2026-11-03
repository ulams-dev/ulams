<?php

namespace Ulams\Reporting\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Ulams\Reporting\Definition\InvalidReportDefinition;
use Ulams\Reporting\Enums\ReportingPermissionsEnum;
use Ulams\Reporting\Models\SavedReport;
use Ulams\Reporting\OData\Csdl;
use Ulams\Reporting\OData\ODataException;
use Ulams\Reporting\OData\QueryOptions;
use Ulams\Reporting\Services\ExportLog;
use Ulams\Reporting\Services\ReportRunner;
use Ulams\Reporting\Services\SavedReports;
use Ulams\Reporting\Support\ReportContext;

/**
 * The read-only OData v4 feed of saved reports, for Excel, Power BI and Tableau (ADR 0194, plan 6.9). One entity set per
 * saved report whose feed an administrator turned on. A report is run with the permissions and scope of the token's user
 * through the same runner as everywhere else, so suppression of small groups, scope and the export log apply. Auth is a
 * `reports:read` access token as Bearer or as the password of HTTP Basic; a token limited to one report reads only that.
 *
 * @OA\Get(path="/api/reporting/odata", operationId="reporting.odata.service.get", summary="OData service document", tags={"Reporting"}, security={{"passport": {}}},
 *     description="OData v4 service document: one entity set Report_{id} per saved report whose feed is on and that the token's user may see (a token limited to one report lists only that one). Auth: a reports:read access token as Bearer, or as the password of HTTP Basic (the user name is ignored), which is how Excel and Power BI send it.",
 *     @OA\Response(response=200, description="The entity sets", @OA\JsonContent(type="object", @OA\Property(property="@odata.context", type="string"), @OA\Property(property="value", type="array", @OA\Items(type="object", @OA\Property(property="name", type="string"), @OA\Property(property="kind", type="string"), @OA\Property(property="url", type="string"))))),
 *     @OA\Response(response=401, description="No or invalid token (with a Basic challenge)"))
 * @OA\Get(path="/api/reporting/odata/$metadata", operationId="reporting.odata.metadata.get", summary="OData metadata document", tags={"Reporting"}, security={{"passport": {}}},
 *     description="The CSDL (XML) of the feed: one entity type per report with a RowNumber key and a property per output column, typed. Capabilities annotations say that only $select, $top, $skip and $count are supported.",
 *     @OA\Response(response=200, description="CSDL XML", @OA\MediaType(mediaType="application/xml", @OA\Schema(type="string"))), @OA\Response(response=401, description="No or invalid token"))
 * @OA\Get(path="/api/reporting/odata/{entity}", operationId="reporting.odata.entity-set.get", summary="Rows of a saved report as an OData entity set", tags={"Reporting"}, security={{"passport": {}}},
 *     description="The rows of the saved report named Report_{id}, 5 000 per page with @odata.nextLink. Supports $select, $top, $skip, $count=true and $format=json. $filter, $orderby, $apply, $expand and the other options answer 501: the saved report is the filter, the grouping and the order. Groups of fewer learners than the threshold are withheld (a row with Withheld true), as in every other output. Each request is recorded as an export of channel feed.",
 *     @OA\Parameter(name="entity", in="path", required=true, description="Report_ followed by the id of the saved report", @OA\Schema(type="string")),
 *     @OA\Parameter(name="$select", in="query", @OA\Schema(type="string")), @OA\Parameter(name="$top", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="$skip", in="query", @OA\Schema(type="integer")), @OA\Parameter(name="$count", in="query", @OA\Schema(type="boolean")),
 *     @OA\Response(response=200, description="OData JSON: @odata.context, value, optional @odata.count and @odata.nextLink", @OA\JsonContent(type="object", @OA\Property(property="@odata.context", type="string"), @OA\Property(property="@odata.count", type="integer"), @OA\Property(property="@odata.nextLink", type="string"), @OA\Property(property="value", type="array", @OA\Items(type="object")))),
 *     @OA\Response(response=401, description="No or invalid token"), @OA\Response(response=403, description="The token's user may not run this report, or the token is limited to another report"),
 *     @OA\Response(response=404, description="No such feed"), @OA\Response(response=409, description="The report needs repair"), @OA\Response(response=501, description="An unsupported query option"))
 */
class ODataController extends Controller
{
    public const PAGE = 5000;

    public function __construct(private readonly SavedReports $reports, private readonly ReportRunner $runner, private readonly ExportLog $log)
    {
    }

    public function serviceDocument(Request $request): JsonResponse
    {
        return $this->guard($request, function () use ($request) {
            $sets = $this->feeds($request)->get()->map(fn (SavedReport $r) => ['name' => Csdl::entitySet($r->id), 'kind' => 'EntitySet', 'url' => Csdl::entitySet($r->id)])->values()->all();

            return $this->json(['@odata.context' => $this->root() . '$metadata', 'value' => $sets]);
        });
    }

    public function metadata(Request $request): Response|JsonResponse
    {
        return $this->guard($request, function () use ($request) {
            $viewer = $request->user();
            $context = new ReportContext($viewer);
            $columns = [];
            $grouped = [];
            foreach ($this->feeds($request)->get() as $report) {
                if (!$this->reports->runnable($report, $viewer) || $this->reports->problems($report) !== []) {
                    continue;
                }
                $described = $this->runner->describe($report->definition, $context);
                $columns[Csdl::entitySet($report->id)] = $described['columns'];
                $grouped[Csdl::entitySet($report->id)] = $described['grouped'];
            }

            return response(Csdl::document($columns, $grouped), 200, ['Content-Type' => 'application/xml; charset=utf-8', 'OData-Version' => '4.0', 'Cache-Control' => 'no-store']);
        });
    }

    public function entitySet(Request $request, string $entity): JsonResponse
    {
        return $this->guard($request, function () use ($request, $entity) {
            $id = preg_match('/^Report_([0-9A-Za-z]{26})$/', $entity, $m) ? $m[1] : throw new ODataException(404, 'There is no feed with this name, or it is not available to this token.');
            $report = $this->feeds($request)->whereKey($id)->first() ?? throw new ODataException(404, 'There is no feed with this name, or it is not available to this token.');
            if (!$this->reports->runnable($report, $request->user())) {
                throw new ODataException(403, 'The user of this token may not run this report.');
            }
            if ($this->reports->problems($report) !== []) {
                throw new ODataException(409, 'This report needs repair before its feed can be read.');
            }

            $context = new ReportContext($request->user());
            $described = $this->runner->describe($report->definition, $context);
            $names = array_column($described['columns'], 'name');
            $properties = ['RowNumber', ...$names, ...($described['grouped'] ? ['Withheld'] : [])];
            $options = QueryOptions::parse($request, $properties);

            $page = min(self::PAGE, $options->top ?? self::PAGE);
            $window = $this->runner->window($report->definition, $context, $options->skip, $page, $options->count);
            $rows = [];
            foreach ($window['rows'] as $i => $row) {
                $entity = ['RowNumber' => $options->skip + $i + 1] + array_intersect_key($row, array_flip($names));
                if ($window['grouped']) {
                    $entity['Withheld'] = (bool) ($row['_withheld'] ?? false);
                }
                $rows[] = $options->select !== null ? array_intersect_key($entity, array_flip($options->select)) : $entity;
            }

            $body = ['@odata.context' => $this->root() . '$metadata#' . Csdl::entitySet($report->id)];
            if ($options->count && $window['total'] !== null) {
                $body['@odata.count'] = $window['total'];
            }
            $body['value'] = $rows;
            $remaining = $options->top !== null ? $options->top - count($rows) : null;
            if ($window['more'] && ($remaining === null || $remaining > 0)) {
                $query = ['$skip' => $options->skip + count($rows)];
                if ($remaining !== null) {
                    $query['$top'] = $remaining;
                }
                if ($options->select !== null) {
                    $query['$select'] = implode(',', $options->select);
                }
                $body['@odata.nextLink'] = $this->root() . Csdl::entitySet($report->id) . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            }

            $this->log->export($request->user()->getAuthIdentifier(), $window['dataset'], $report->definition, 'odata', count($rows), $report->id, 'feed');
            $this->reports->markRun($report);

            return $this->json($body);
        });
    }

    /** The feeds this request may see: on, visible to the user, and within a limited token's resources. */
    private function feeds(Request $request)
    {
        abort_unless((bool) $request->user()?->can(ReportingPermissionsEnum::READ, 'api'), 403);

        return $this->reports->feedsFor($request->user(), $request->attributes->get('ulams.token_resource_ids'));
    }

    /** Runs an action and answers OData errors the OData way. */
    private function guard(Request $request, \Closure $action): Response|JsonResponse
    {
        try {
            return $action();
        } catch (ODataException $e) {
            return $e->response();
        } catch (InvalidReportDefinition) {
            return (new ODataException(409, 'This report needs repair before its feed can be read.'))->response();
        } catch (AuthorizationException) {
            return (new ODataException(403, 'The user of this token may not run this report.'))->response();
        }
    }

    private function root(): string
    {
        return url('/api/reporting/odata') . '/';
    }

    /** @param array<string,mixed> $body */
    private function json(array $body): JsonResponse
    {
        return response()->json($body, 200, ['Content-Type' => 'application/json;odata.metadata=minimal;odata.streaming=true;IEEE754Compatible=false;charset=utf-8', 'OData-Version' => '4.0', 'Cache-Control' => 'no-store'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
