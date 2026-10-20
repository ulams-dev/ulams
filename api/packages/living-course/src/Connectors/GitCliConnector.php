<?php

namespace Ulams\LivingCourse\Connectors;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Opis\JsonSchema\Validator;
use Ulams\Core\Http\SafeHttp;
use Ulams\Core\Http\UnsafeUrlException;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\LivingCourse\Connectors\Git\FileLimits;
use Ulams\LivingCourse\Connectors\Git\GitRunner;
use Ulams\LivingCourse\Connectors\Git\PathFilter;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Services\RevisionService;

/**
 * Any Git server that speaks smart HTTP(S), read with the `git` command line (ADR 0032, for hosts
 * without a supported API). It is read-only and sandboxed:
 *
 * - the address passes the same SSRF checks as every other outbound request (https, public addresses,
 *   no credentials in the URL, the tenant's host allow-list) and the checked address is pinned for git,
 *   redirects are off and only the https protocol is allowed;
 * - the clone is shallow, single-branch, without checkout, hooks, submodules, LFS or tags, in a fresh
 *   temporary directory that is always removed; its size and run time are capped while it runs;
 * - git runs with an empty environment, no prompts and no user configuration; the access token travels
 *   in an environment variable as a request header, never in arguments, URLs, logs or error messages;
 * - only regular files are read (symlinks and submodules are skipped) as blobs by id, and git's own
 *   messages are never shown: they come from the remote and are untrusted.
 *
 * Off by default: add `git-cli` to `LIVING_COURSE_CONNECTORS` on an image that ships git.
 */
final class GitCliConnector implements SourceConnector
{
    /**
     * @param bool $allowLocal tests only: read a repository on this machine through `file://`, without network checks
     */
    public function __construct(private readonly bool $allowLocal = false, private readonly string $binary = 'git')
    {
    }

    public function key(): string
    {
        return 'git-cli';
    }

    public function label(): string
    {
        return 'Git repository on any server';
    }

    public function configSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['url'],
            'properties' => [
                'url' => ['type' => 'string', 'minLength' => 8, 'maxLength' => 500, 'pattern' => $this->allowLocal ? '^(https?|file)://' : '^https?://'],
                'branch' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255, 'pattern' => '^[^\s~^:?*\[\\\\-][^\s~^:?*\[\\\\]*$'],
                'username' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100, 'pattern' => '^[A-Za-z0-9_.@-]+$'],
                'paths' => ['type' => 'array', 'maxItems' => 20, 'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255]],
                'extensions' => ['type' => 'array', 'maxItems' => 10, 'items' => ['type' => 'string', 'pattern' => '^[a-z0-9]{1,8}$']],
            ],
        ];
    }

    public function secretFields(): array
    {
        return ['token'];
    }

    /** @return array{url:string,branch:string,username:string,paths:string[],extensions:string[]} */
    public static function normalise(array $config): array
    {
        return [
            'url' => trim((string) ($config['url'] ?? '')),
            'branch' => (string) ($config['branch'] ?? 'main'),
            'username' => (string) ($config['username'] ?? 'git'),
            'paths' => array_values((array) ($config['paths'] ?? ['**/*.md'])),
            'extensions' => array_values((array) ($config['extensions'] ?? ['md', 'markdown', 'mdx', 'txt'])),
        ];
    }

    public function validate(array $config, array $secrets): void
    {
        $result = (new Validator())->validate(json_decode((string) json_encode($config)), json_decode((string) json_encode($this->configSchema())));
        if (!$result->isValid()) {
            throw new ConnectorException('The repository settings are not valid: check the address (https://…), the branch and the paths.');
        }
        $c = self::normalise($config);
        $this->assertAvailable();
        $filter = new PathFilter($c['paths'], $c['extensions']);
        $this->withClone($c, $secrets['token'] ?? null, function (string $dir, string $head, array $tree) use ($filter) {
            $matching = array_values(array_filter($tree, fn ($f) => $filter->accepts($f['path'])));
            if ($matching === []) {
                throw new ConnectorException('No file in this branch matches the paths and extensions you chose.');
            }
            FileLimits::assert($matching);
        });
    }

    public function fetch(Connection $connection, ?Revision $latest): FetchResult
    {
        $c = self::normalise((array) $connection->config);
        $token = ((array) $connection->secrets)['token'] ?? null;
        $this->assertAvailable();
        $remote = $this->remoteHead($c, $token);
        if ($latest !== null && $latest->origin_ref === $remote) {
            return FetchResult::unchanged($remote);
        }
        $filter = new PathFilter($c['paths'], $c['extensions']);

        return $this->withClone($c, $token, function (string $dir, string $head, array $tree, \Closure $git) use ($c, $filter, $latest) {
            $tree = array_values(array_filter($tree, fn ($f) => $filter->accepts($f['path'])));
            if ($tree === []) {
                throw new ConnectorException('No file in this branch matches the paths and extensions you chose.');
            }
            FileLimits::assert($tree);
            $known = [];
            foreach ((array) ($latest?->metadata['files'] ?? []) as $f) {
                if (isset($f['blob'], $f['path'])) {
                    $known[$f['path']] = $f['blob'];
                }
            }
            $limits = (array) config('living_course.connector_limits');
            $total = 0;
            $files = [];
            foreach ($tree as $entry) {
                $bytes = ($latest !== null && ($known[$entry['path']] ?? null) === $entry['blob']) ? $this->stored($latest, $entry['path']) : null;
                $bytes ??= $git(['cat-file', 'blob', $entry['blob']])['stdout'];
                if (strlen($bytes) > (int) $limits['file_bytes']) {
                    throw new ConnectorException(sprintf('%s is larger than %d KB, the limit for one source file.', $entry['path'], (int) $limits['file_bytes'] / 1024));
                }
                $total += strlen($bytes);
                if ($total > (int) $limits['total_bytes']) {
                    throw new ConnectorException(sprintf('The selected files add up to more than %d MB. Narrow the paths.', (int) $limits['total_bytes'] / 1048576));
                }
                $files[] = new FetchedFile($entry['path'], $bytes, 'markdown', $entry['blob']);
            }

            return new FetchResult(false, $head, $files, ['branch' => $c['branch'], 'fileCount' => count($files)]);
        });
    }

    /** Hosts without a push-event format we know accept the generic signed delivery of a custom sender. */
    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function verifyWebhook(Connection $connection, Request $request): ?WebhookDelivery
    {
        $c = self::normalise((array) $connection->config);
        $secret = (string) (((array) $connection->secrets)['webhook_secret'] ?? '');
        $body = $request->getContent();
        $given = strtolower((string) preg_replace('/^sha256=/', '', (string) $request->header('X-Ulams-Signature')));
        if ($secret === '' || $given === '' || !hash_equals(hash_hmac('sha256', $body, $secret), $given)) {
            throw new InvalidSignature('The webhook signature does not match.');
        }
        $payload = json_decode($body, true);
        if (is_array($payload) && isset($payload['ref']) && $payload['ref'] !== 'refs/heads/' . $c['branch']) {
            return null;
        }
        $id = $request->header('X-Ulams-Delivery') ?? (is_array($payload) ? ($payload['after'] ?? null) : null) ?? hash('sha256', $body);

        return new WebhookDelivery(mb_substr((string) $id, 0, 100));
    }

    /* ------------------------------------------------------------------ git */

    private function assertAvailable(): void
    {
        if (!GitRunner::available($this->binary)) {
            throw new ConnectorException('The git program is not installed on this server, so this kind of source is not available.');
        }
    }

    /**
     * The checked address, the git options that keep it that way and the access header.
     *
     * @param array{url:string,branch:string,username:string,paths:string[],extensions:string[]} $c
     * @return array{url:string,options:string[],env:array<string,string>}
     * @throws ConnectorException
     */
    private function target(array $c, ?string $token): array
    {
        $options = ['-c', 'core.hooksPath=/dev/null', '-c', 'core.fsmonitor=false', '-c', 'core.symlinks=false', '-c', 'credential.helper=', '-c', 'submodule.recurse=false', '-c', 'gc.auto=0'];
        $url = $c['url'];
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($this->allowLocal && $scheme === 'file') {
            return ['url' => $url, 'options' => [...$options, '-c', 'protocol.file.allow=always'], 'env' => ['GIT_ALLOW_PROTOCOL' => 'file']];
        }
        $parts = parse_url($url) ?: [];
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new ConnectorException('The address must not contain a user name or a password. Use the token field instead.');
        }
        $limits = ['allowed_hosts' => (array) config('living_course.allowed_hosts', []), 'insecure_hosts' => (array) config('living_course.insecure_hosts', []), 'label' => 'Git servers'];
        try {
            $resolve = SafeHttp::check($url, $limits);
        } catch (UnsafeUrlException $e) {
            throw new ConnectorException($e->getMessage());
        }
        $options = [...$options, '-c', 'protocol.allow=never', '-c', 'protocol.https.allow=always', '-c', 'http.followRedirects=false', '-c', 'http.proxy=', '-c', 'http.sslVerify=true',
            '-c', 'http.lowSpeedLimit=1000', '-c', 'http.lowSpeedTime=30'];
        $protocols = 'https';
        if ($scheme === 'http') {
            $options = [...$options, '-c', 'protocol.http.allow=always'];
            $protocols = 'https:http';
        }
        if ($resolve !== null) {
            $options = [...$options, '-c', 'http.curloptResolve=' . $resolve];
        }
        $env = ['GIT_ALLOW_PROTOCOL' => $protocols];
        if ($token !== null && $token !== '') {
            // a header from the environment (git 2.31+): not in arguments, not in .git/config, and never followed to another host
            $env += ['GIT_CONFIG_COUNT' => '1', 'GIT_CONFIG_KEY_0' => 'http.extraHeader', 'GIT_CONFIG_VALUE_0' => 'Authorization: Basic ' . base64_encode($c['username'] . ':' . $token)];
        }

        return ['url' => $url, 'options' => $options, 'env' => $env];
    }

    /** @param array{url:string,branch:string,username:string,paths:string[],extensions:string[]} $c */
    private function remoteHead(array $c, ?string $token): string
    {
        $target = $this->target($c, $token);
        $home = $this->workDir();
        try {
            $result = (new GitRunner($this->binary, $target['env']))->run([...$target['options'], 'ls-remote', '--heads', '--', $target['url'], 'refs/heads/' . $c['branch']], null, $home, $this->timeout());
        } finally {
            File::deleteDirectory($home);
        }
        $this->assertOk($result);
        foreach (explode("\n", trim($result['stdout'])) as $line) {
            [$sha, $ref] = array_pad(preg_split('/\s+/', trim($line)) ?: [], 2, '');
            if ($ref === 'refs/heads/' . $c['branch'] && preg_match('/^[0-9a-f]{40,64}$/', $sha)) {
                return $sha;
            }
        }
        throw new ConnectorException('The branch was not found in this repository.');
    }

    /**
     * Clones into a fresh directory, lists the regular files of the head commit and hands them, with a
     * way to run git in the clone, to `$use`. The directory is removed afterwards, whatever happens.
     *
     * @param array{url:string,branch:string,username:string,paths:string[],extensions:string[]} $c
     * @param callable(string,string,array<int,array{path:string,blob:string,size:int}>,\Closure(array<int,string>):array{code:int,stdout:string,stderr:string}):mixed $use
     */
    private function withClone(array $c, ?string $token, callable $use): mixed
    {
        $target = $this->target($c, $token);
        $home = $this->workDir();
        $dir = $home . '/repo';
        $git = new GitRunner($this->binary, $target['env']);
        try {
            $clone = $git->run([...$target['options'], 'clone', '--quiet', '--depth', '1', '--single-branch', '--branch', $c['branch'], '--no-tags', '--no-checkout', '--no-recurse-submodules', '--template=', '--', $target['url'], $dir], $home, $home, $this->timeout(), 65536, $this->sizeWatch($home));
            $this->assertOk($clone);
            $inside = fn (array $args) => $this->checked($git->run([...$target['options'], '-C', $dir, ...$args], $dir, $home, $this->timeout(), 16 * 1048576));
            $head = trim($inside(['rev-parse', 'HEAD'])['stdout']);
            if (!preg_match('/^[0-9a-f]{40,64}$/', $head)) {
                throw new ConnectorException('The repository could not be read.');
            }
            $list = $inside(['ls-tree', '-r', '-l', '-z', '--full-tree', 'HEAD'])['stdout'];

            return $use($dir, $head, $this->parseTree($list), $inside);
        } finally {
            File::deleteDirectory($home);
        }
    }

    /**
     * Regular files only: symlinks (120000), submodules (160000) and directories never become source.
     *
     * @return array<int,array{path:string,blob:string,size:int}>
     */
    private function parseTree(string $list): array
    {
        $files = [];
        foreach (explode("\0", $list) as $row) {
            if ($row === '' || !preg_match('/^(\d{6}) blob ([0-9a-f]{40,64})\s+(\d+|-)\t(.+)$/s', $row, $m)) {
                continue;
            }
            if (!in_array($m[1], ['100644', '100755'], true)) {
                continue;
            }
            $files[] = ['path' => $m[4], 'blob' => $m[2], 'size' => (int) $m[3]];
        }
        usort($files, fn ($a, $b) => strcmp($a['path'], $b['path']));

        return $files;
    }

    /** @param array{code:int,stdout:string,stderr:string} $result */
    private function checked(array $result): array
    {
        $this->assertOk($result);

        return $result;
    }

    /**
     * Maps the exit status to a message of ours. What git printed is never shown: it can carry text
     * chosen by the remote (and, in theory, the address with credentials).
     *
     * @param array{code:int,stdout:string,stderr:string} $result
     * @throws ConnectorException
     */
    private function assertOk(array $result): void
    {
        if ($result['code'] === 0) {
            return;
        }
        $err = strtolower($result['stderr']);
        $refused = preg_match('/error: (401|403)\b/', $err) === 1;
        throw new ConnectorException(match (true) {
            $refused || str_contains($err, 'authentication failed') || str_contains($err, 'terminal prompts disabled') || str_contains($err, 'could not read username') => 'The server refused the access token, or the repository needs one. Check the token and its read permission.',
            str_contains($err, 'remote branch') => 'The branch was not found in this repository.',
            str_contains($err, 'not found') || str_contains($err, 'error: 404') => 'The repository was not found at this address, or this token cannot see it.',
            str_contains($err, 'could not resolve host') => 'The address of the server could not be resolved.',
            str_contains($err, 'ssl') || str_contains($err, 'certificate') => 'The server certificate could not be verified.',
            str_contains($err, 'timed out') || str_contains($err, 'timeout') => 'The server did not answer in time.',
            default => sprintf('The repository could not be read (git exit code %d).', $result['code']),
        });
    }

    private function timeout(): int
    {
        return max(1, (int) config('living_course.git_cli.timeout', 120));
    }

    /** Stops a clone that grows past the limit. @return callable():?string */
    private function sizeWatch(string $home): callable
    {
        $max = (int) config('living_course.git_cli.clone_bytes', 200 * 1048576);

        return function () use ($home, $max): ?string {
            $size = 0;
            if (!is_dir($home . '/repo')) {
                return null;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($home . '/repo', \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $size += $file->isFile() ? $file->getSize() : 0;
                if ($size > $max) {
                    return sprintf('The repository is larger than %d MB. Use a smaller repository or a mirror with only the documentation.', $max / 1048576);
                }
            }

            return null;
        };
    }

    private function workDir(): string
    {
        $dir = rtrim(sys_get_temp_dir(), '/') . '/ulams-git-' . bin2hex(random_bytes(8));
        if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new ConnectorException('A working directory could not be created.');
        }

        return $dir;
    }

    /** The bytes of a file as stored with the previous revision (an unchanged blob is not downloaded again). */
    private function stored(Revision $revision, string $path): ?string
    {
        $disk = Storage::disk(SourceIngestor::disk());
        $file = RevisionService::rawFilePath($revision, $path);

        return $file !== null && $disk->exists($file) ? (string) $disk->get($file) : null;
    }
}
