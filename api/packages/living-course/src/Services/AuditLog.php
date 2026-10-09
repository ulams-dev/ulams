<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Models\Session;
use Ulams\LivingCourse\Models\AuditEntry;

/**
 * The only writer of `living_course_audit` (ADR 0034). Every row carries the hash of the previous
 * row, so editing or removing a row breaks the chain from there on; `verify()` recomputes it. The
 * row lock on the one-row head table serialises writers, and callers record inside the same
 * transaction as the change they describe.
 */
final class AuditLog
{
    public const ACTIONS = [
        'connection.created', 'connection.updated', 'connection.disconnected', 'connection.secret_rotated',
        'webhook.rejected',
        'revision.detected', 'revision.no_impact', 'revision.failed', 'revision.promoted',
        'proposal.created', 'proposal.analysed', 'proposal.superseded', 'proposal.rejected', 'proposal.applied',
        'item.accepted', 'item.rejected', 'item.regenerated', 'item.reset',
        'progress.rules_applied', 'notice.created',
    ];

    private const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    private const FIELDS = [
        'session_id', 'course_id', 'actor_type', 'actor_id', 'on_behalf_of', 'action', 'subject_type', 'subject_id', 'source_id',
        'revision_id', 'origin_ref', 'version_from', 'version_to', 'ai_call_ids', 'data', 'ip', 'user_agent', 'created_at',
    ];

    /**
     * @param array<string,mixed> $context session_id, course_id, actor_type, actor_id, on_behalf_of,
     *     subject_type, subject_id, source_id, revision_id, origin_ref, version_from, version_to,
     *     ai_call_ids, data (never secrets), ip, user_agent
     */
    public function record(string $action, array $context = []): AuditEntry
    {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException("Unknown audit action {$action}.");
        }

        return DB::transaction(function () use ($action, $context) {
            DB::table('living_course_audit_head')->insertOrIgnore(['id' => 1, 'last_id' => 0, 'last_hash' => self::GENESIS]);
            $row = $this->row($action, $context);
            $head = DB::table('living_course_audit_head')->where('id', 1)->lockForUpdate()->first();
            $prev = $head->last_hash ?? self::GENESIS;
            $row['prev_hash'] = $prev;
            $row['hash'] = self::hashOf($prev, $row);
            $stored = $row;
            foreach (['ai_call_ids', 'data'] as $json) {
                $stored[$json] = $row[$json] === null ? null : json_encode($row[$json], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $id = (int) DB::table('living_course_audit')->insertGetId($stored);
            DB::table('living_course_audit_head')->where('id', 1)->update(['last_id' => $id, 'last_hash' => $row['hash']]);

            return AuditEntry::query()->findOrFail($id);
        });
    }

    /** @return array<string,mixed> */
    private function row(string $action, array $c): array
    {
        $user = Auth::guard('api')->user() ?? Auth::user();
        $sessionId = $c['session_id'] ?? null;
        $courseId = $c['course_id'] ?? ($sessionId !== null ? Session::withTrashed()->whereKey($sessionId)->value('course_id') : null);
        $actorType = $c['actor_type'] ?? ($user !== null ? 'user' : 'system');
        $request = app()->runningInConsole() ? null : request();

        return [
            'session_id' => $sessionId,
            'course_id' => $courseId !== null ? (int) $courseId : null,
            'actor_type' => $actorType,
            'actor_id' => array_key_exists('actor_id', $c) ? $c['actor_id'] : ($actorType === 'user' && $user !== null ? (int) $user->getAuthIdentifier() : null),
            'on_behalf_of' => $c['on_behalf_of'] ?? null,
            'action' => $action,
            'subject_type' => $c['subject_type'] ?? null,
            'subject_id' => isset($c['subject_id']) ? (string) $c['subject_id'] : null,
            'source_id' => $c['source_id'] ?? null,
            'revision_id' => $c['revision_id'] ?? null,
            'origin_ref' => isset($c['origin_ref']) ? mb_substr((string) $c['origin_ref'], 0, 128) : null,
            'version_from' => $c['version_from'] ?? null,
            'version_to' => $c['version_to'] ?? null,
            'ai_call_ids' => ($c['ai_call_ids'] ?? []) === [] ? null : array_values($c['ai_call_ids']),
            'data' => ($c['data'] ?? []) === [] ? null : $c['data'],
            'ip' => $c['ip'] ?? $request?->ip(),
            'user_agent' => isset($c['user_agent']) ? mb_substr((string) $c['user_agent'], 0, 255) : ($request !== null ? mb_substr((string) $request->userAgent(), 0, 255) ?: null : null),
            'created_at' => Carbon::now()->format('Y-m-d H:i:s.u'),
        ];
    }

    /** sha256(prev_hash || canonical JSON of the row without its hashes). */
    public static function hashOf(string $prev, array $row): string
    {
        $canonical = [];
        foreach (self::FIELDS as $field) {
            $value = $row[$field] ?? null;
            if ($field === 'created_at' && $value !== null) {
                $value = Carbon::parse($value)->format('Y-m-d H:i:s.u');
            }
            if ($field === 'course_id' || $field === 'actor_id' || $field === 'on_behalf_of' || $field === 'version_from' || $field === 'version_to') {
                $value = $value === null ? null : (int) $value;
            }
            $canonical[$field] = is_array($value) ? self::sorted($value) : $value;
        }

        return hash('sha256', $prev . json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function sorted(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = self::sorted($v);
            }
        }

        return $value;
    }

    /**
     * Recomputes the whole chain.
     *
     * @return array{ok:bool,checked:int,brokenId:?int,reason:?string}
     */
    public function verify(): array
    {
        $prev = self::GENESIS;
        $checked = 0;
        $lastId = 0;
        foreach (DB::table('living_course_audit')->orderBy('id')->cursor() as $row) {
            $data = (array) $row;
            $stored = ['prev_hash' => $data['prev_hash'], 'hash' => $data['hash']];
            foreach (['ai_call_ids', 'data'] as $json) {
                $data[$json] = $data[$json] === null ? null : json_decode($data[$json], true);
            }
            if ($stored['prev_hash'] !== $prev) {
                return ['ok' => false, 'checked' => $checked, 'brokenId' => (int) $data['id'], 'reason' => 'The link to the previous entry does not match (an entry was removed or reordered).'];
            }
            if (self::hashOf($prev, $data) !== $stored['hash']) {
                return ['ok' => false, 'checked' => $checked, 'brokenId' => (int) $data['id'], 'reason' => 'The entry was changed after it was written.'];
            }
            $prev = $stored['hash'];
            $lastId = (int) $data['id'];
            $checked++;
        }
        $head = DB::table('living_course_audit_head')->where('id', 1)->first();
        if ($head !== null && ((int) $head->last_id !== $lastId || $head->last_hash !== $prev)) {
            return ['ok' => false, 'checked' => $checked, 'brokenId' => $lastId + 1, 'reason' => 'Entries at the end of the trail are missing.'];
        }

        return ['ok' => true, 'checked' => $checked, 'brokenId' => null, 'reason' => null];
    }
}
