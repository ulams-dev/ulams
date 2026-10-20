<?php

namespace Ulams\Compliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Compliance\Models\ReportSchedule;
use Ulams\Compliance\Services\ComplianceReportService;

/**
 * Scheduled reports: a compliance dataset e-mailed as CSV or JSON lines every day, week or month to
 * tenant users. Permission `compliance_report`.
 *
 * @OA\Get(path="/api/admin/compliance/schedules", summary="List scheduled reports", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="schedules"), @OA\Response(response=403, description="Forbidden"))
 * @OA\Post(path="/api/admin/compliance/schedules", summary="Schedule a report", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", required={"name","dataset","frequency","recipient_ids"},
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="dataset", type="string", enum={"assignments","certificates","audit"}),
 *         @OA\Property(property="frequency", type="string", enum={"daily","weekly","monthly"}),
 *         @OA\Property(property="format", type="string", enum={"csv","jsonl"}),
 *         @OA\Property(property="filters", type="object", description="course_id, user_id, status, from, to"),
 *         @OA\Property(property="recipient_ids", type="array", description="Ids of users of this tenant", @OA\Items(type="integer"))))),
 *     @OA\Response(response=201, description="the schedule; the first run is the next 06:00"), @OA\Response(response=422, description="Validation error"))
 * @OA\Put(path="/api/admin/compliance/schedules/{id}", summary="Update a scheduled report", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="the schedule"), @OA\Response(response=404, description="Unknown schedule"))
 * @OA\Delete(path="/api/admin/compliance/schedules/{id}", summary="Delete a scheduled report", tags={"Compliance"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="deleted"), @OA\Response(response=404, description="Unknown schedule"))
 */
class ScheduleController extends ComplianceController
{
    public function __construct(private ComplianceReportService $reports)
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
            'dataset' => [$presence, Rule::in(ComplianceReportService::DATASETS)],
            'frequency' => [$presence, Rule::in(ReportSchedule::FREQUENCIES)],
            'format' => ['sometimes', Rule::in(['csv', 'jsonl'])],
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
        if (isset($data['dataset'])) {
            abort_unless($this->reports->available($data['dataset']), 422, 'Unknown or unavailable dataset.');
        }
        if (array_key_exists('filters', $data)) {
            $data['filters'] = $this->reports->normalise($data['filters'] ?? []);
        }
        if (isset($data['frequency']) && !$required) {
            $data['next_run_at'] = ReportSchedule::nextRun($data['frequency'], now()->subDay());
        }

        return $data;
    }

    private function present(ReportSchedule $schedule): array
    {
        return [
            'id' => $schedule->getKey(), 'name' => $schedule->name, 'dataset' => $schedule->dataset, 'frequency' => $schedule->frequency,
            'format' => $schedule->format, 'filters' => $schedule->filters ?? (object) [], 'recipient_ids' => $schedule->recipient_ids, 'active' => $schedule->active,
            'next_run_at' => $schedule->next_run_at?->toIso8601String(), 'last_sent_at' => $schedule->last_sent_at?->toIso8601String(),
        ];
    }
}
