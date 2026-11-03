<?php

namespace Ulams\Compliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Compliance\Models\ReportSchedule;
use Ulams\Compliance\Services\ComplianceReportService;
use Ulams\Reporting\Services\SavedReports;

/**
 * Scheduled reports: a compliance dataset, or a saved report of the report builder, e-mailed as CSV, JSON lines or (saved
 * reports) XLSX every day, week or month to tenant users. A saved report runs once per recipient with that recipient's own
 * permissions. Permission `compliance_report`.
 *
 * @OA\Get(path="/api/admin/compliance/schedules", operationId="admin.compliance.schedules.get", summary="List scheduled reports", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="schedules", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 * @OA\Post(path="/api/admin/compliance/schedules", operationId="admin.compliance.schedules.post", summary="Schedule a report", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", required={"name","frequency","recipient_ids"},
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="dataset", type="string", enum={"assignments","certificates","audit"}, description="A compliance dataset; give this or saved_report_id"),
 *         @OA\Property(property="saved_report_id", type="string", description="A saved report the caller can see (report builder); it runs once per recipient with the recipient's own permissions"),
 *         @OA\Property(property="frequency", type="string", enum={"daily","weekly","monthly"}),
 *         @OA\Property(property="format", type="string", enum={"csv","jsonl","xlsx"}, description="xlsx only for a saved report"),
 *         @OA\Property(property="filters", type="object", description="course_id, user_id, status, from, to (datasets only)"),
 *         @OA\Property(property="recipient_ids", type="array", description="Ids of users of this tenant", @OA\Items(type="integer"))))),
 *     @OA\Response(response=201, description="the schedule; the first run is the next 06:00", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=422, description="Validation error"))
 * @OA\Put(path="/api/admin/compliance/schedules/{id}", operationId="admin.compliance.schedules.by-id.put", summary="Update a scheduled report", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="the schedule", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=404, description="Unknown schedule"))
 * @OA\Delete(path="/api/admin/compliance/schedules/{id}", operationId="admin.compliance.schedules.by-id.delete", summary="Delete a scheduled report", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="deleted", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=404, description="Unknown schedule"))
 */
class ScheduleController extends ComplianceController
{
    public function __construct(private ComplianceReportService $reports, private SavedReports $savedReports)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::REPORT);

        return $this->ok(ReportSchedule::query()->orderBy('name')->get()->map(fn (ReportSchedule $s) => $this->present($s))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::REPORT);
        $data = $this->validated($request, true);
        $schedule = ReportSchedule::query()->create($data + [
            'format' => 'csv',
            'created_by' => $request->user()->getKey(),
            'next_run_at' => ReportSchedule::nextRun($data['frequency'], now()->subDay()),
        ]);

        return $this->ok($this->present($schedule), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::REPORT);
        $schedule = ReportSchedule::query()->findOrFail($id);
        $schedule->fill($this->validated($request, false))->save();

        return $this->ok($this->present($schedule));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->guard($request, CompliancePermissionsEnum::REPORT);
        ReportSchedule::query()->findOrFail($id)->delete();

        return $this->ok(null);
    }

    private function validated(Request $request, bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';
        $data = $request->validate([
            'name' => [$presence, 'string', 'max:255'],
            'dataset' => ['sometimes', 'nullable', 'string', Rule::in(ComplianceReportService::DATASETS)],
            'saved_report_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'frequency' => [$presence, Rule::in(ReportSchedule::FREQUENCIES)],
            'format' => ['sometimes', Rule::in(ReportSchedule::REPORT_FORMATS)],
            'active' => ['sometimes', 'boolean'],
            'filters' => ['sometimes', 'nullable', 'array'],
            'filters.course_id' => ['sometimes', 'integer'],
            'filters.user_id' => ['sometimes', 'integer'],
            'filters.status' => ['sometimes', 'string', 'max:16'],
            'filters.from' => ['sometimes', 'date'],
            'filters.to' => ['sometimes', 'date'],
            'recipient_ids' => [$presence, 'array', 'min:1', 'max:50'],
            'recipient_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        if ($required && empty($data['dataset']) === empty($data['saved_report_id'])) {
            throw ValidationException::withMessages(['dataset' => ['Give a dataset or a saved report, not both and not neither.']]);
        }
        if (!empty($data['saved_report_id'])) {
            $report = $this->savedReports->find($data['saved_report_id'], $request->user());
            if ($report === null) {
                throw ValidationException::withMessages(['saved_report_id' => ['Unknown saved report.']]);
            }
            // the schedule follows the report; the dataset column is kept for the list and the export log
            $data['dataset'] = (string) ($report->definition['dataset'] ?? '');
            $data['filters'] = null;
            if ($this->savedReports->problems($report) !== []) {
                throw ValidationException::withMessages(['saved_report_id' => ['This report needs repair before it can be scheduled.']]);
            }
        } elseif (array_key_exists('saved_report_id', $data) || (!$required && !empty($data['dataset']))) {
            // detached from the report: it is a dataset schedule again
            $data['saved_report_id'] = null;
        }
        if (!empty($data['dataset']) && empty($data['saved_report_id'])) {
            abort_unless($this->reports->available($data['dataset']), 422, 'Unknown or unavailable dataset.');
        }
        $forSavedReport = !empty($data['saved_report_id']) || (!array_key_exists('saved_report_id', $data) && !$required && $this->isSavedReportSchedule($request));
        if (($data['format'] ?? null) === 'xlsx' && !$forSavedReport) {
            throw ValidationException::withMessages(['format' => ['XLSX is available for saved reports only.']]);
        }
        if (array_key_exists('filters', $data) && $data['filters'] !== null) {
            $data['filters'] = $this->reports->normalise($data['filters'] ?? []);
        }
        if (isset($data['frequency']) && !$required) {
            $data['next_run_at'] = ReportSchedule::nextRun($data['frequency'], now()->subDay());
        }

        return $data;
    }

    private function isSavedReportSchedule(Request $request): bool
    {
        $id = (int) $request->route('id');

        return $id > 0 && ReportSchedule::query()->whereKey($id)->whereNotNull('saved_report_id')->exists();
    }

    private function present(ReportSchedule $schedule): array
    {
        return [
            'id' => $schedule->getKey(), 'name' => $schedule->name, 'dataset' => $schedule->dataset, 'frequency' => $schedule->frequency,
            'format' => $schedule->format, 'saved_report_id' => $schedule->saved_report_id, 'skipped_recipient_ids' => $schedule->skipped_recipient_ids ?? [], 'filters' => $schedule->filters ?? (object) [], 'recipient_ids' => $schedule->recipient_ids, 'active' => $schedule->active,
            'next_run_at' => $schedule->next_run_at?->toIso8601String(), 'last_sent_at' => $schedule->last_sent_at?->toIso8601String(),
        ];
    }
}
