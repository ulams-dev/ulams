<?php

namespace Ulams\KnowledgeExport\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Courses\Models\Course;
use Ulams\KnowledgeExport\Enums\KnowledgePermissionEnum;
use Ulams\KnowledgeExport\Jobs\ExportKnowledgeJob;
use Ulams\KnowledgeExport\Models\KnowledgeExport;
use Ulams\KnowledgeExport\Services\CoursePackBuilder;
use Ulams\KnowledgeExport\Services\KnowledgeExporter;

/**
 * Knowledge packs for company RAG (Phase 7.6). Admin only (`knowledge_manage`); packs live in the tenant's own
 * database and storage, so an id from another tenant never resolves.
 *
 * @OA\Schema(schema="KnowledgeExport", type="object",
 *     @OA\Property(property="id", type="string", example="01j9z8x3v7c5qk2m4n6p8r0t1w"),
 *     @OA\Property(property="scope", type="string", enum={"course","tenant"}),
 *     @OA\Property(property="course_id", type="integer", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"queued","running","ready","failed"}),
 *     @OA\Property(property="trigger", type="string", enum={"manual","auto"}),
 *     @OA\Property(property="pack_hash", type="string", nullable=true, description="SHA-256 over the chunk ids, text hashes and citations; unchanged content gives the same hash"),
 *     @OA\Property(property="courses", type="integer"),
 *     @OA\Property(property="chunks", type="integer"),
 *     @OA\Property(property="uncited_chunks", type="integer", description="Chunks without source citations (hand-written or edited text)"),
 *     @OA\Property(property="error", type="string", nullable=true),
 *     @OA\Property(property="files", type="array", @OA\Items(type="string", enum={"manifest","chunks","index"}), description="Names for the download endpoint"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="finished_at", type="string", format="date-time", nullable=true))
 *
 * @OA\Get(path="/api/admin/knowledge/exports", operationId="knowledge.exports.list", summary="Knowledge exports, newest first", tags={"Admin Knowledge"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Exports", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/KnowledgeExport")))),
 *     @OA\Response(response=403, description="Needs knowledge_manage"))
 * @OA\Post(path="/api/admin/knowledge/exports", operationId="knowledge.exports.create", summary="Start a knowledge export of one course or of the whole tenant", tags={"Admin Knowledge"}, security={{"passport": {}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"scope"},
 *         @OA\Property(property="scope", type="string", enum={"course","tenant"}),
 *         @OA\Property(property="course_id", type="integer", description="Required for scope course"))),
 *     @OA\Response(response=202, description="Queued", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/KnowledgeExport"))),
 *     @OA\Response(response=403, description="Needs knowledge_manage"),
 *     @OA\Response(response=422, description="Unknown course, or a course learners cannot see"))
 * @OA\Get(path="/api/admin/knowledge/exports/{export}", operationId="knowledge.exports.get", summary="One knowledge export", tags={"Admin Knowledge"}, security={{"passport": {}}},
 *     @OA\Parameter(name="export", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Export", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/KnowledgeExport"))),
 *     @OA\Response(response=404, description="Not found"))
 * @OA\Get(path="/api/admin/knowledge/exports/{export}/download", operationId="knowledge.exports.download", summary="Download a file of a ready export (manifest.json, chunks.jsonl or index.json)", tags={"Admin Knowledge"}, security={{"passport": {}}},
 *     @OA\Parameter(name="export", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="file", in="query", required=true, @OA\Schema(type="string", enum={"manifest","chunks","index"})),
 *     @OA\Parameter(name="course", in="query", description="Course of a tenant export (for manifest and chunks)", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="The file (JSON, or JSON Lines for chunks)", @OA\MediaType(mediaType="application/x-ndjson", @OA\Schema(type="string"))),
 *     @OA\Response(response=404, description="Not found, or the export is not ready"))
 * @OA\Get(path="/api/admin/knowledge/courses/{course}/latest", operationId="knowledge.courses.latest", summary="The latest ready export of a course", tags={"Admin Knowledge"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Export", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/KnowledgeExport"))),
 *     @OA\Response(response=404, description="The course was never exported"))
 */
class KnowledgeExportController extends UlamsBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate(['course_id' => ['nullable', 'integer']]);
        $rows = KnowledgeExport::query()
            ->when($data['course_id'] ?? null, fn ($q, $v) => $q->where('course_id', $v))
            ->orderByDesc('created_at')->orderByDesc('id')->limit(50)->get();

        return $this->sendResponse($rows->map(fn (KnowledgeExport $e) => self::present($e))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'scope' => ['required', Rule::in([KnowledgeExport::COURSE, KnowledgeExport::TENANT])],
            'course_id' => ['required_if:scope,course', 'prohibited_if:scope,tenant', 'integer', 'exists:courses,id'],
        ]);
        if ($data['scope'] === KnowledgeExport::COURSE) {
            $course = Course::query()->findOrFail($data['course_id']);
            if (!CoursePackBuilder::exportable($course)) {
                throw ValidationException::withMessages(['course_id' => 'Only published courses that learners can see are exported.']);
            }
        }
        $export = KnowledgeExport::query()->create([
            'scope' => $data['scope'],
            'course_id' => $data['scope'] === KnowledgeExport::COURSE ? $data['course_id'] : null,
            'status' => KnowledgeExport::QUEUED,
            'trigger' => 'manual',
            'requested_by' => (int) $request->user()->getKey(),
        ]);
        ExportKnowledgeJob::enqueue($export->id);

        return $this->sendResponse(self::present($export->refresh()), 'Export queued.', 202);
    }

    public function show(Request $request, string $export): JsonResponse
    {
        $this->authorizeManage($request);

        return $this->sendResponse(self::present(KnowledgeExport::query()->findOrFail(strtolower($export))));
    }

    public function latest(Request $request, int $course): JsonResponse
    {
        $this->authorizeManage($request);
        $export = KnowledgeExport::query()
            ->where('scope', KnowledgeExport::COURSE)->where('course_id', $course)->where('status', KnowledgeExport::READY)
            ->orderByDesc('finished_at')->orderByDesc('id')->first();
        if ($export === null) {
            throw new NotFoundHttpException('This course has no knowledge export yet.');
        }

        return $this->sendResponse(self::present($export));
    }

    public function download(Request $request, string $export): StreamedResponse
    {
        $this->authorizeManage($request);
        $row = KnowledgeExport::query()->findOrFail(strtolower($export));
        $data = $request->validate([
            'file' => ['required', Rule::in(self::files($row))],
            'course' => ['nullable', 'integer'],
        ]);
        if ($row->status !== KnowledgeExport::READY || $row->directory === null) {
            throw new NotFoundHttpException('The export is not ready.');
        }
        $name = match ($data['file']) {
            'manifest' => 'manifest.json',
            'chunks' => 'chunks.jsonl',
            default => 'index.json',
        };
        $relative = $name;
        if ($row->scope === KnowledgeExport::TENANT && $data['file'] !== 'index') {
            if (!isset($data['course'])) {
                throw ValidationException::withMessages(['course' => 'A tenant export has one pack per course; say which.']);
            }
            $relative = 'courses/' . (int) $data['course'] . '/' . $name;
        }
        $disk = KnowledgeExporter::disk();
        $path = KnowledgeExporter::path($row, $relative);
        if (!$disk->exists($path)) {
            throw new NotFoundHttpException('No such file in this export.');
        }

        return $disk->download($path, $name, ['Content-Type' => $name === 'chunks.jsonl' ? 'application/x-ndjson' : 'application/json']);
    }

    private function authorizeManage(Request $request): void
    {
        if (!$request->user()?->can(KnowledgePermissionEnum::KNOWLEDGE_MANAGE)) {
            throw new AccessDeniedHttpException('Knowledge exports need the knowledge_manage permission.');
        }
    }

    /** @return list<string> */
    private static function files(KnowledgeExport $e): array
    {
        return $e->scope === KnowledgeExport::COURSE ? ['manifest', 'chunks'] : ['index', 'manifest', 'chunks'];
    }

    /** @return array<string,mixed> */
    private static function present(KnowledgeExport $e): array
    {
        return [
            'id' => $e->id,
            'scope' => $e->scope,
            'course_id' => $e->course_id,
            'status' => $e->status,
            'trigger' => $e->trigger,
            'pack_hash' => $e->pack_hash,
            'courses' => $e->courses,
            'chunks' => $e->chunks,
            'uncited_chunks' => $e->uncited,
            'error' => $e->error,
            'files' => $e->status === KnowledgeExport::READY ? self::files($e) : [],
            'created_at' => $e->created_at?->toIso8601String(),
            'finished_at' => $e->finished_at?->toIso8601String(),
        ];
    }
}
