<?php

namespace Ulams\Identity\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Ulams\Identity\Models\IdentityAudit;

/**
 * The only writer of `identity_audit` (plan 4.10). Every row carries the hash of the previous row, so editing
 * or removing a row breaks the chain from there on; `verify()` recomputes it. The row lock on the one-row head
 * table serialises writers.
 *
 * Rows hold reason codes, ids and hashes, never a token, assertion, code, secret or password.
 */
final class IdentityAuditLog
{
    public const ACTIONS = [
        'sso.login', 'sso.login_failed', 'sso.link',
        'provider.created', 'provider.updated', 'provider.deleted',
    ];

    public const ACTORS = ['user', 'scim', 'hris', 'system', 'idp'];

    private const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    private const FIELDS = [
        'actor', 'actor_id', 'provider_id', 'action', 'subject_type', 'subject_id', 'data', 'ip', 'user_agent', 'request_id', 'created_at',
    ];

    /**
     * @param array<string,mixed> $context actor, actor_id, provider_id, subject_type, subject_id, data (never secrets), ip, user_agent, request_id
     */
    public function record(string $action, array $context = []): IdentityAudit
    {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException("Unknown identity audit action {$action}.");
        }

        return DB::transaction(function () use ($action, $context) {
            DB::table('identity_audit_head')->insertOrIgnore(['id' => 1, 'last_id' => 0, 'last_hash' => self::GENESIS]);
            $row = $this->row($action, $context);
            $head = DB::table('identity_audit_head')->where('id', 1)->lockForUpdate()->first();
            $prev = $head->last_hash ?? self::GENESIS;
            $row['prev_hash'] = $prev;
            $row['hash'] = self::hashOf($prev, $row);
            $stored = $row;
            $stored['data'] = $row['data'] === null ? null : json_encode($row['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $id = (int) DB::table('identity_audit')->insertGetId($stored);
            DB::table('identity_audit_head')->where('id', 1)->update(['last_id' => $id, 'last_hash' => $row['hash']]);

            return IdentityAudit::query()->findOrFail($id);
        });
    }

    /** @return array<string,mixed> */
    private function row(string $action, array $c): array
    {
        $user = Auth::guard('api')->user();
        $actor = $c['actor'] ?? ($user !== null ? 'user' : 'system');
        $request = app()->runningInConsole() ? null : request();

        return [
            'actor' => $actor,
            'actor_id' => array_key_exists('actor_id', $c) ? $c['actor_id'] : ($actor === 'user' && $user !== null ? (int) $user->getAuthIdentifier() : null),
            'provider_id' => $c['provider_id'] ?? null,
            'action' => $action,
            'subject_type' => $c['subject_type'] ?? null,
            'subject_id' => isset($c['subject_id']) ? (string) $c['subject_id'] : null,
            'data' => ($c['data'] ?? []) === [] ? null : $c['data'],
            'ip' => $c['ip'] ?? $request?->ip(),
            'user_agent' => isset($c['user_agent']) ? mb_substr((string) $c['user_agent'], 0, 255) : ($request !== null ? mb_substr((string) $request->userAgent(), 0, 255) ?: null : null),
            'request_id' => $c['request_id'] ?? ($request !== null ? mb_substr((string) ($request->header('X-Request-Id') ?? ''), 0, 64) ?: null : null),
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
            if ($field === 'actor_id') {
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
     * @return array{ok:bool,checked:int,broken_id:?int,reason:?string}
     */
    public function verify(): array
    {
        $prev = self::GENESIS;
        $checked = 0;
        $lastId = 0;
        foreach (DB::table('identity_audit')->orderBy('id')->cursor() as $row) {
            $data = (array) $row;
            $storedPrev = $data['prev_hash'];
            $storedHash = $data['hash'];
            $data['data'] = $data['data'] === null ? null : json_decode($data['data'], true);
            if ($storedPrev !== $prev) {
                return ['ok' => false, 'checked' => $checked, 'broken_id' => (int) $data['id'], 'reason' => 'The link to the previous entry does not match (an entry was removed or reordered).'];
            }
            if (self::hashOf($prev, $data) !== $storedHash) {
                return ['ok' => false, 'checked' => $checked, 'broken_id' => (int) $data['id'], 'reason' => 'The entry was changed after it was written.'];
            }
            $prev = $storedHash;
            $lastId = (int) $data['id'];
            $checked++;
        }
        $head = DB::table('identity_audit_head')->where('id', 1)->first();
        if ($head !== null && ((int) $head->last_id !== $lastId || $head->last_hash !== $prev)) {
            return ['ok' => false, 'checked' => $checked, 'broken_id' => $lastId + 1, 'reason' => 'Entries at the end of the trail are missing.'];
        }

        return ['ok' => true, 'checked' => $checked, 'broken_id' => null, 'reason' => null];
    }
}
