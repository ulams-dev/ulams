<?php

namespace Ulams\Reporting\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Ulams\Reporting\Definition\InvalidReportDefinition;
use Ulams\Reporting\Enums\ReportingPermissionsEnum;
use Ulams\Reporting\Export\ReportExporter;
use Ulams\Reporting\Models\SavedReport;
use Ulams\Reporting\Services\ReportRunner;
use Ulams\Reporting\Services\SavedReports;
use Ulams\Reporting\Support\ReportContext;

/**
 * Saved reports (ADR 0194, plan 6.8): a named report definition, private or shared with tenant roles. Sharing shares
 * the definition, not the data: whoever runs a report runs it with their own permissions and scope.
 *
 * @OA\Get(path="/api/admin/reporting/saved", operationId="admin.reporting.reports.list", summary="Saved reports the caller can see", tags={"Reporting"}, security={{"passport": {}}},
 *     description="The caller's own reports and the reports shared with one of their roles (administrators with reporting_manage see all). Each has can_edit, runnable (the caller holds the permission of its dataset) and needs_repair with the problems when its dataset or a column it uses no longer exists. Needs reporting_read.",
 *     @OA\Response(response=200, description="Saved reports", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 * @OA\Post(path="/api/admin/reporting/saved", operationId="admin.reporting.reports.create", summary="Save a report", tags={"Reporting"}, security={{"passport": {}}},
 *     description="Saves a report definition (see the query endpoint) under a name. visibility is private (the default) or roles with shared_roles naming tenant roles. The caller must hold reporting_create and the permission of the definition's dataset.",
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", required={"name","definition"},
 *         @OA\Property(property="name", type="string"), @OA\Property(property="description", type="string"),
 *         @OA\Property(property="visibility", type="string", enum={"private","roles"}),
 *         @OA\Property(property="shared_roles", type="array", description="Role names of this tenant, for example tutor", @OA\Items(type="string")),
 *         @OA\Property(property="definition", type="object", description="A report definition, as for POST /api/admin/reporting/query")))),
 *     @OA\Response(response=201, description="The saved report", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=422, description="Invalid definition or sharing"))
 * @OA\Get(path="/api/admin/reporting/saved/{id}", operationId="admin.reporting.reports.by-id.get", summary="One saved report", tags={"Reporting"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="The saved report with its definition", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=404, description="Not found, or not visible to the caller"))
 * @OA\Put(path="/api/admin/reporting/saved/{id}", operationId="admin.reporting.reports.by-id.put", summary="Change a saved report", tags={"Reporting"}, security={{"passport": {}}},
 *     description="Only the owner (who holds reporting_create) or an administrator with reporting_manage. Any of name, description, visibility, shared_roles and definition.",
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object",
 *         @OA\Property(property="name", type="string"), @OA\Property(property="description", type="string"),
 *         @OA\Property(property="visibility", type="string", enum={"private","roles"}),
 *         @OA\Property(property="shared_roles", type="array", @OA\Items(type="string")),
 *         @OA\Property(property="definition", type="object")))),
 *     @OA\Response(response=200, description="The saved report", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Not the owner"), @OA\Response(response=404, description="Not found"), @OA\Response(response=422, description="Invalid definition or sharing"))
 * @OA\Delete(path="/api/admin/reporting/saved/{id}", operationId="admin.reporting.reports.by-id.delete", summary="Delete a saved report", tags={"Reporting"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Not the owner"), @OA\Response(response=404, description="Not found"))
 * @OA\Get(path="/api/admin/reporting/saved/{id}/run", operationId="admin.reporting.reports.by-id.run", summary="Run a saved report", tags={"Reporting"}, security={{"passport": {}}},
 *     description="Runs the report with the caller's own permissions and scope. format=json (default) answers like the query endpoint (500 rows and the total); csv, jsonl and xlsx stream every row up to 1 000 000 (an XLSX sheet holds 1 048 575) and are recorded as exports. Groups of fewer learners than the threshold are withheld in every format. 403 when the caller lacks the permission of the dataset, 409 when the report needs repair.",
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="format", in="query", @OA\Schema(type="string", enum={"json","csv","jsonl","xlsx"}, default="json")),
 *     @OA\Response(response=200, description="json: columns, rows and meta; csv, jsonl and xlsx: a file", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Not found"), @OA\Response(response=409, description="The report needs repair"))
 */
class SavedReportController extends Controller
{
    public function __construct(
        private readonly SavedReports $reports,
        private readonly ReportRunner $runner,
        private readonly ReportExporter $exporter,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->allow($request, ReportingPermissionsEnum::READ);
        $viewer = $request->user();

        return $this->ok($this->reports->visibleTo($viewer)->get()->map(fn (SavedReport $r) => $this->present($r, $request))->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow($request, ReportingPermissionsEnum::CREATE);
        $report = $this->guarded(fn () => $this->reports->create($request->user(), $this->input($request, true)));

        return $this->ok($this->present($report, $request), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->allow($request, ReportingPermissionsEnum::READ);

        return $this->ok($this->present($this->visible($request, $id), $request));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->allow($request, ReportingPermissionsEnum::READ);
        $report = $this->visible($request, $id);
        abort_unless($this->reports->canEdit($report, $request->user()), 403, 'Only the owner or an administrator who manages reports can change this report.');
        $report = $this->guarded(fn () => $this->reports->update($report, $request->user(), $this->input($request, false)));

        return $this->ok($this->present($report, $request));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->allow($request, ReportingPermissionsEnum::READ);
        $report = $this->visible($request, $id);
        abort_unless($this->reports->canEdit($report, $request->user()), 403, 'Only the owner or an administrator who manages reports can delete this report.');
        $this->reports->delete($report);

        return $this->ok(null);
    }

    public function run(Request $request, string $id): JsonResponse|StreamedResponse
    {
        $this->allow($request, ReportingPermissionsEnum::READ);
        $report = $this->visible($request, $id);
        $format = $request->validate(['format' => ['sometimes', Rule::in(['json', ...ReportExporter::FORMATS])]])['format'] ?? 'json';
        abort_unless($this->reports->runnable($report, $request->user()), 403, 'You cannot run this report: you do not have the permission its dataset needs.');
        if (($problems = $this->reports->problems($report)) !== []) {
            return response()->json(['success' => false, 'message' => 'This report needs repair: ' . $problems[0]['path'] . ' ' . $problems[0]['message'], 'errors' => $problems], 409);
        }
        $context = new ReportContext($request->user());

        if ($format !== 'json') {
            $this->reports->markRun($report);
            $name = sprintf('%s-%s.%s', str($report->name)->slug('-') ?: 'report', now()->format('Ymd-His'), $format);

            return response()->streamDownload(function () use ($report, $context, $format) {
                $out = fopen('php://output', 'w');
                $this->exporter->write($report->definition, $context, $format, $out, $report->id);
                fclose($out);
            }, $name, ['Content-Type' => ReportExporter::MIME[$format], 'Cache-Control' => 'no-store']);
        }

        $result = $this->guarded(fn () => $this->runner->preview($report->definition, $context));
        $this->reports->markRun($report);

        return response()->json(['success' => true, 'data' => ['columns' => $result['columns'], 'rows' => $result['rows']], 'meta' => $result['meta'], 'message' => 'OK']);
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless((bool) $request->user()?->can($permission, 'api'), 403);
    }

    private function visible(Request $request, string $id): SavedReport
    {
        return $this->reports->find($id, $request->user()) ?? abort(404);
    }

    /** @return array<string,mixed> */
    private function input(Request $request, bool $creating): array
    {
        $presence = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$presence, 'string', 'min:1', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'visibility' => ['sometimes', Rule::in([SavedReport::PRIVATE, SavedReport::ROLES])],
            'shared_roles' => ['sometimes', 'nullable', 'array', 'max:20'],
            'shared_roles.*' => ['string', 'max:64'],
            'definition' => [$presence, 'array'],
        ]);
    }

    /** Definition problems are a 422 with the path of each. */
    private function guarded(\Closure $action): mixed
    {
        try {
            return $action();
        } catch (InvalidReportDefinition $e) {
            throw ValidationException::withMessages(array_combine(
                array_map(fn (string $key) => 'definition.' . $key, array_keys($e->messages())),
                array_values($e->messages()),
            ));
        } catch (AuthorizationException) {
            abort(403, 'You cannot run reports on this dataset.');
        }
    }

    /** @return array<string,mixed> */
    private function present(SavedReport $report, Request $request): array
    {
        $viewer = $request->user();
        $problems = $this->reports->problems($report);

        return [
            'id' => $report->id,
            'name' => $report->name,
            'description' => $report->description,
            'dataset' => $report->definition['dataset'] ?? null,
            'visibility' => $report->visibility,
            'shared_roles' => $report->shared_roles ?? [],
            'definition' => $report->definition,
            'definition_version' => $report->definition_version,
            'owner_id' => $report->owner_user_id,
            'is_owner' => (int) $report->owner_user_id === (int) $viewer->getAuthIdentifier(),
            'can_edit' => $this->reports->canEdit($report, $viewer),
            'runnable' => $problems === [] && $this->reports->runnable($report, $viewer),
            'needs_repair' => $problems !== [],
            'problems' => $problems,
            'last_run_at' => $report->last_run_at?->toIso8601String(),
            'created_at' => $report->created_at?->toIso8601String(),
            'updated_at' => $report->updated_at?->toIso8601String(),
        ];
    }

    private function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => 'OK'], $status);
    }
}
