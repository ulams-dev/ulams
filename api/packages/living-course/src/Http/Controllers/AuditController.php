<?php

namespace Ulams\LivingCourse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Ulams\LivingCourse\Http\Controllers\Concerns\ResolvesLivingCourse;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Services\AuditLog;

/**
 * The audit trail (ADR 0034): who decided what and when, tied to the source revision and the
 * blueprint versions. Per session for its author and admins; tenant-wide for admins.
 *
 * @OA\Get(path="/api/admin/living-course/sessions/{session}/audit", summary="Audit entries of a session (filters: action, actorType, from, to, source)", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="entries"), @OA\Response(response=403, description="not allowed"))
 * @OA\Get(path="/api/admin/living-course/sessions/{session}/audit/export", summary="Export the audit entries of a session as CSV or JSON", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="format", in="query", @OA\Schema(type="string", enum={"csv","json"})), @OA\Response(response=200, description="file"))
 * @OA\Get(path="/api/admin/living-course/sessions/{session}/audit/verify", summary="Verify the hash chain of the audit trail", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="verdict"))
 * @OA\Get(path="/api/admin/living-course/audit/export", summary="Tenant-wide audit export (admins)", tags={"Admin Living Course"}, security={{"passport": {}}}, @OA\Response(response=200, description="file"), @OA\Response(response=403, description="admins only"))
 * @OA\Get(path="/api/admin/living-course/audit/verify", summary="Tenant-wide chain verification (admins)", tags={"Admin Living Course"}, security={{"passport": {}}}, @OA\Response(response=200, description="verdict"), @OA\Response(response=403, description="admins only"))
 */
class AuditController extends Controller
{
    use ResolvesLivingCourse;

    private const COLUMNS = ['id', 'created_at', 'action', 'actor_type', 'actor_id', 'on_behalf_of', 'subject_type', 'subject_id', 'session_id', 'course_id', 'source_id', 'revision_id', 'origin_ref', 'version_from', 'version_to', 'ai_call_ids', 'data', 'ip', 'prev_hash', 'hash'];

    public function __construct(private readonly AuditLog $audit)
    {
    }

    public function index(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session);
        $query = $this->filtered($request, AuditEntry::query()->where('session_id', $s->id));
        $page = $query->orderByDesc('id')->paginate(min(200, max(1, (int) $request->query('perPage', 50))));
        $names = $this->names($page->getCollection());

        return self::ok([
            'entries' => $page->getCollection()->map(fn (AuditEntry $e) => self::present($e, $names))->all(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'perPage' => $page->perPage(),
        ]);
    }

    public function verify(Request $request, string $session): JsonResponse
    {
        $this->sessionFor($request, $session);

        return self::ok($this->audit->verify());
    }

    public function export(Request $request, string $session): StreamedResponse
    {
        $s = $this->sessionFor($request, $session);

        return $this->stream($request, $this->filtered($request, AuditEntry::query()->where('session_id', $s->id)), "living-course-audit-{$s->id}");
    }

    public function exportAll(Request $request): StreamedResponse
    {
        $this->assertAdmin($request);

        return $this->stream($request, $this->filtered($request, AuditEntry::query()), 'living-course-audit');
    }

    public function verifyAll(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        return self::ok($this->audit->verify());
    }

    private function assertAdmin(Request $request): void
    {
        $user = $request->user();
        if (!method_exists($user, 'hasRole') || !$user->hasRole('admin')) {
            throw new AccessDeniedHttpException('Only admins can read the audit trail of the whole academy.');
        }
    }

    private function filtered(Request $request, Builder $query): Builder
    {
        return $query
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], (string) $a) . '%'))
            ->when($request->query('actorType'), fn ($q, $a) => $q->where('actor_type', $a))
            ->when($request->query('source'), fn ($q, $a) => $q->where('source_id', $a))
            ->when($request->query('from'), fn ($q, $a) => $q->where('created_at', '>=', $a))
            ->when($request->query('to'), fn ($q, $a) => $q->where('created_at', '<=', $a));
    }

    private function stream(Request $request, Builder $query, string $name): StreamedResponse
    {
        $format = $request->query('format') === 'json' ? 'json' : 'csv';
        $names = fn ($rows) => $this->names($rows);

        return response()->streamDownload(function () use ($query, $format, $names) {
            $out = fopen('php://output', 'w');
            if ($format === 'csv') {
                fputcsv($out, [...self::COLUMNS, 'actor_name']);
            } else {
                fwrite($out, '[');
            }
            $first = true;
            foreach ($query->orderBy('id')->cursor()->chunk(500) as $chunk) {
                $labels = $names($chunk);
                foreach ($chunk as $e) {
                    /** @var AuditEntry $e */
                    $row = [];
                    foreach (self::COLUMNS as $c) {
                        $v = $e->getAttribute($c);
                        $row[$c] = $c === 'created_at' ? $e->created_at?->format('Y-m-d\TH:i:s.u\Z') : $v;
                    }
                    $row['actor_name'] = $labels[$e->actor_id] ?? null;
                    if ($format === 'csv') {
                        fputcsv($out, array_map(fn ($v) => self::csvCell(is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $v), $row));
                    } else {
                        fwrite($out, ($first ? '' : ',') . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        $first = false;
                    }
                }
            }
            if ($format === 'json') {
                fwrite($out, ']');
            }
            fclose($out);
        }, "{$name}.{$format}", ['Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/json']);
    }

    /** A spreadsheet must never run a cell as a formula. */
    private static function csvCell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }

    /** @return array<int,string> user id => name */
    private function names($entries): array
    {
        $ids = collect($entries)->pluck('actor_id')->filter()->unique()->values()->all();
        if ($ids === []) {
            return [];
        }
        $model = config('auth.providers.users.model');

        return $model::query()->whereIn('id', $ids)->get()->mapWithKeys(fn ($u) => [$u->getKey() => trim((string) ($u->name ?? $u->email ?? '')) ?: ('#' . $u->getKey())])->all();
    }

    /** @param array<int,string> $names @return array<string,mixed> */
    private static function present(AuditEntry $e, array $names): array
    {
        return [
            'id' => $e->id,
            'at' => $e->created_at?->toIso8601String(),
            'action' => $e->action,
            'actor' => ['type' => $e->actor_type, 'id' => $e->actor_id, 'name' => $e->actor_id !== null ? ($names[$e->actor_id] ?? null) : null, 'onBehalfOf' => $e->on_behalf_of],
            'subject' => ['type' => $e->subject_type, 'id' => $e->subject_id],
            'sourceId' => $e->source_id,
            'revisionId' => $e->revision_id,
            'originRef' => $e->origin_ref,
            'versionFrom' => $e->version_from,
            'versionTo' => $e->version_to,
            'aiCallIds' => $e->ai_call_ids ?? [],
            'data' => $e->data ?? [],
            'hash' => $e->hash,
            'prevHash' => $e->prev_hash,
        ];
    }
}
