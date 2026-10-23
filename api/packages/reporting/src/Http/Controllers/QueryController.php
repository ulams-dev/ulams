<?php

namespace Ulams\Reporting\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Ulams\Reporting\Definition\InvalidReportDefinition;
use Ulams\Reporting\Enums\ReportingPermissionsEnum;
use Ulams\Reporting\Services\ReportRunner;
use Ulams\Reporting\Support\ReportContext;

/**
 * Preview of a report definition (ADR 0194, plan 6.7): the first 500 rows plus the total. It reads data but changes
 * nothing, so a token with `reports:read` may call it although it is a POST. Groups of fewer learners than the
 * threshold are withheld on the server and say so.
 *
 * @OA\Post(path="/api/admin/reporting/query", operationId="admin.reporting.query.run", summary="Preview a report definition", tags={"Reporting"}, security={{"passport": {}}},
 *     description="Runs a report definition against one dataset with the caller's own permissions and returns at most 500 rows and the total. A row-level report lists `columns`; a grouped report lists `groupBy` and `aggregates` and returns the groups followed by their aggregates. On a dataset that holds learners, every group with fewer distinct learners than the threshold (10 by default) is withheld: it is replaced, together with the smallest remaining group when needed so that it cannot be derived by subtraction, by one row marked `_withheld`. Needs reporting_read and the permission of the dataset (see the datasets endpoint). 422 lists what is wrong with the definition, by path.",
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", required={"dataset"},
 *         @OA\Property(property="dataset", type="string", description="Key of a dataset, for example assignments", example="assignments"),
 *         @OA\Property(property="columns", type="array", description="Row-level report: the columns to list", @OA\Items(type="string")),
 *         @OA\Property(property="filters", type="array", description="All filters apply (AND). op is eq, neq, in, not_in, lt, lte, gt, gte, between, contains or is_null; value is a scalar, a list (in, not_in), two values (between) or a boolean (is_null)", @OA\Items(type="object", required={"column","op"},
 *             @OA\Property(property="column", type="string"), @OA\Property(property="op", type="string"), @OA\Property(property="value"))),
 *         @OA\Property(property="dateRange", type="object", description="Restrict a date column to a preset (last_7_days, last_30_days, last_90_days, last_12_months, this_month, last_month, this_quarter, this_year, last_year) or to from and to dates (YYYY-MM-DD, inclusive)", required={"column"},
 *             @OA\Property(property="column", type="string"), @OA\Property(property="preset", type="string"), @OA\Property(property="from", type="string", format="date"), @OA\Property(property="to", type="string", format="date")),
 *         @OA\Property(property="groupBy", type="array", description="A column name, or {column, bucket} with bucket day, week, month, quarter or year on a date column (the output column is named column_bucket)", @OA\Items(oneOf={@OA\Schema(type="string"), @OA\Schema(type="object")})),
 *         @OA\Property(property="aggregates", type="array", @OA\Items(type="object", required={"fn","as"},
 *             @OA\Property(property="fn", type="string", enum={"count","count_distinct","sum","avg","min","max"}), @OA\Property(property="column", type="string"), @OA\Property(property="as", type="string", description="Name of the output column, lower case letters, digits and underscores"))),
 *         @OA\Property(property="sort", type="array", description="Output columns, first one first", @OA\Items(type="object", required={"column"}, @OA\Property(property="column", type="string"), @OA\Property(property="dir", type="string", enum={"asc","desc"}))),
 *         @OA\Property(property="limit", type="integer", description="Most rows the report returns"),
 *     ))),
 *     @OA\Response(response=200, description="columns (name, type, label), rows and meta: mode, total_rows, preview_rows, truncated, withheld_groups, min_group_size", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=403, description="No reporting_read, or no permission for the dataset"), @OA\Response(response=422, description="The definition is invalid"))
 */
class QueryController extends Controller
{
    public function __construct(private readonly ReportRunner $runner)
    {
    }

    public function run(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()?->can(ReportingPermissionsEnum::READ, 'api'), 403);
        $input = $request->json()->all();

        try {
            $result = $this->runner->preview($input, new ReportContext($request->user()));
        } catch (InvalidReportDefinition $e) {
            throw ValidationException::withMessages($e->messages());
        } catch (AuthorizationException) {
            abort(403, 'You cannot run reports on this dataset.');
        }

        return response()->json(['success' => true, 'data' => ['columns' => $result['columns'], 'rows' => $result['rows']], 'meta' => $result['meta'], 'message' => 'OK']);
    }
}
