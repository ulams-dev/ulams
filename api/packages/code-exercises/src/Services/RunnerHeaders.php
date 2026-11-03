<?php

namespace Ulams\CodeExercises\Services;

use Ulams\Interactive\Services\InteractiveCsp;
use Ulams\Uploads\Http\ContentHeaderProvider;

/**
 * Headers of the browser runner's files on the content origin (ADR 0170, ADR 0086): static files under
 * runtime/code-runner/<version>/ that ship with the API. Only the files listed in
 * `ulams_code_exercises.runner.files` are served, so nothing else in that folder can be reached.
 *
 * Two pages run learner code, each in an opaque (sandboxed) frame, one inside the other:
 *  - runner.html bundles the exercise with esbuild-wasm. It needs `connect-src 'self'` for the wasm file
 *    and `'wasm-unsafe-eval'`, and it never runs learner code itself.
 *  - exec.html runs the bundle in a Worker. Its policy has no `connect-src` at all, so the code has no
 *    network (no fetch, XHR, WebSocket, image or frame requests), and no `'unsafe-eval'`: the bundle is
 *    loaded as a blob script.
 * Both set `sandbox allow-scripts`. Only the runner may be framed by nothing but the tenant's own pages: exec.html is
 * framed by the (sandboxed, so opaque) runner, which no `frame-ancestors` source can match, so it has none; it has no
 * network and an opaque origin, and runs only code that arrives with the nonce of its URL fragment.
 */
class RunnerHeaders implements ContentHeaderProvider
{
    public static function runnerCsp(): string
    {
        return self::policy('runner');
    }

    public static function execCsp(): string
    {
        return self::policy('exec');
    }

    /** The policies are data (resources/runner-csp.json), shared with the front-end tests. */
    private static function policy(string $page): string
    {
        $all = json_decode((string) file_get_contents(__DIR__ . '/../../resources/runner-csp.json'), true, 512, JSON_THROW_ON_ERROR);
        $ancestors = InteractiveCsp::frameAncestors();
        $directives = array_map(fn (string $d) => str_replace('{ancestors}', $ancestors, $d), $all[$page]);

        return self::join($directives);
    }

    public function headersFor(string $path): ?array
    {
        if (!config('ulams_code_exercises.enabled') || !preg_match('#^runtime/code-runner/([0-9]+)/([a-z0-9.-]+)$#', $path, $m)) {
            return null;
        }
        if ((int) $m[1] !== (int) config('ulams_code_exercises.runner.version') || !in_array($m[2], (array) config('ulams_code_exercises.runner.files'), true)) {
            return null;
        }

        $headers = [
            'Referrer-Policy' => 'no-referrer',
            // a version's files never change
            'Cache-Control' => 'public, max-age=3600',
        ];
        $csp = match ($m[2]) {
            'runner.html' => self::runnerCsp(),
            'exec.html' => self::execCsp(),
            default => (string) json_decode((string) file_get_contents(__DIR__ . '/../../resources/runner-csp.json'), true)['other'],
        };

        return ['Content-Security-Policy' => $csp] + $headers;
    }

    /** @param string[] $directives */
    private static function join(array $directives): string
    {
        $api = rtrim((string) config('app.url'), '/');
        if ($api !== '') {
            $directives[] = 'report-uri ' . $api . '/api/csp-report';
        }

        return implode('; ', $directives);
    }
}
