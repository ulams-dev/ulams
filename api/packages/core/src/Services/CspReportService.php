<?php

namespace Ulams\Core\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Ulams\Core\Models\CspReport;
use Ulams\Core\Services\Contracts\CspReportServiceContract;

/**
 * Collects CSP violation reports (ADR 0044). Reports come from browsers without any credentials, so
 * everything in them is untrusted: only a directive name from a fixed shape, the host of the blocked
 * resource and the path of the page are kept, truncated, aggregated into one row with a counter.
 */
class CspReportService implements CspReportServiceContract
{
    /** Violations processed per request; browsers send one, the Reporting API batches a few. */
    public const MAX_PER_REQUEST = 20;

    private const KEYWORDS = ['inline', 'eval', 'data', 'blob', 'self', 'wasm-eval', 'trusted-types-policy', 'trusted-types-sink'];

    public function record(array $payload): int
    {
        $recorded = 0;

        foreach (array_slice($this->violations($payload), 0, self::MAX_PER_REQUEST) as $violation) {
            $row = $this->normalise($violation);
            if ($row === null) {
                continue;
            }

            DB::transaction(function () use ($row) {
                $now = now();
                CspReport::query()->insertOrIgnore($row + ['count' => 0, 'first_seen_at' => $now, 'last_seen_at' => $now]);
                CspReport::query()->where($row)->increment('count', 1, ['last_seen_at' => $now]);
            });
            $recorded++;
        }

        return $recorded;
    }

    public function list(?int $perPage): LengthAwarePaginator
    {
        return CspReport::query()->orderByDesc('last_seen_at')->orderBy('id')->paginate(min(max($perPage ?? 25, 1), 100));
    }

    public function prune(int $days): int
    {
        return CspReport::query()->where('last_seen_at', '<', now()->subDays($days))->delete();
    }

    /**
     * `application/csp-report`: {"csp-report": {...}}; `application/reports+json`: a list of
     * {"type": "csp-violation", "body": {...}}. Returns the violation objects in a common shape.
     *
     * @return list<array{directive: mixed, blocked: mixed, document: mixed}>
     */
    private function violations(array $payload): array
    {
        if (isset($payload['csp-report']) && is_array($payload['csp-report'])) {
            $r = $payload['csp-report'];

            return [[
                'directive' => $r['effective-directive'] ?? $r['violated-directive'] ?? null,
                'blocked' => $r['blocked-uri'] ?? null,
                'document' => $r['document-uri'] ?? null,
            ]];
        }

        $list = array_is_list($payload) ? $payload : [$payload];
        $out = [];
        foreach ($list as $entry) {
            if (!is_array($entry) || ($entry['type'] ?? null) !== 'csp-violation' || !is_array($entry['body'] ?? null)) {
                continue;
            }
            $b = $entry['body'];
            $out[] = [
                'directive' => $b['effectiveDirective'] ?? $b['violatedDirective'] ?? null,
                'blocked' => $b['blockedURL'] ?? $b['blockedURI'] ?? null,
                'document' => $b['documentURL'] ?? $b['documentURI'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param array{directive: mixed, blocked: mixed, document: mixed} $violation
     * @return array{directive: string, blocked_host: string, document_path: string}|null
     */
    private function normalise(array $violation): ?array
    {
        if (!is_string($violation['directive'] ?? null) || !is_string($violation['document'] ?? null)) {
            return null;
        }

        // `script-src-elem 'self' ...` or `script-src-elem`: the first token, a plain directive name
        $directive = strtolower(strtok(trim($violation['directive']), ' ') ?: '');
        if (!preg_match('/^[a-z][a-z-]{1,40}$/', $directive)) {
            return null;
        }

        $documentPath = parse_url($violation['document'], PHP_URL_PATH);
        $documentPath = is_string($documentPath) && $documentPath !== '' ? $documentPath : '/';

        return [
            'directive' => $directive,
            'blocked_host' => $this->blockedHost($violation['blocked'] ?? null),
            'document_path' => mb_substr($documentPath, 0, 255),
        ];
    }

    private function blockedHost(mixed $blocked): string
    {
        if (!is_string($blocked) || trim($blocked) === '') {
            return 'inline';
        }

        $blocked = strtolower(trim($blocked));
        if (in_array($blocked, self::KEYWORDS, true)) {
            return $blocked;
        }

        $parts = parse_url($blocked);
        if (is_array($parts) && isset($parts['host'])) {
            return mb_substr($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''), 0, 255);
        }
        // data:, blob:, about: ... carry no host: keep the scheme only
        if (is_array($parts) && isset($parts['scheme'])) {
            return mb_substr($parts['scheme'], 0, 32);
        }

        return 'unknown';
    }
}
