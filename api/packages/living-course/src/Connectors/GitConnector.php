<?php

namespace Ulams\LivingCourse\Connectors;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Opis\JsonSchema\Validator;
use Ulams\Core\Http\SafeHttp;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\LivingCourse\Connectors\Git\GiteaClient;
use Ulams\LivingCourse\Connectors\Git\GitHostClient;
use Ulams\LivingCourse\Connectors\Git\GitHubClient;
use Ulams\LivingCourse\Connectors\Git\GitLabClient;
use Ulams\LivingCourse\Connectors\Git\PathFilter;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Services\RevisionService;

/**
 * A branch of a Git repository on GitHub, GitLab or Gitea/Forgejo, read through the host's REST API
 * (ADR 0032): no `git` binary, no clone. The head commit is the cheap change check; only files whose
 * blob changed are downloaded, the rest comes from the previous revision's stored copy. Files are
 * concatenated in path order; the file path is the first element of every fragment's heading path.
 */
final class GitConnector implements SourceConnector
{
    public const HOSTS = ['github' => 'https://api.github.com', 'gitlab' => 'https://gitlab.com/api/v4', 'gitea' => null];

    /** @param (callable(mixed):mixed)|null $handler Guzzle handler (tests) */
    public function __construct(private readonly mixed $handler = null)
    {
    }

    public function key(): string
    {
        return 'git';
    }

    public function label(): string
    {
        return 'Git repository';
    }

    public function configSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['host', 'repository'],
            'properties' => [
                'host' => ['type' => 'string', 'enum' => array_keys(self::HOSTS)],
                'base_url' => ['type' => 'string', 'maxLength' => 255, 'pattern' => '^https?://'],
                'repository' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 255, 'pattern' => '^[A-Za-z0-9_.\-]+(/[A-Za-z0-9_.\-]+)+$'],
                'branch' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255, 'pattern' => '^[^\s~^:?*\[\\\\]+$'],
                'paths' => ['type' => 'array', 'maxItems' => 20, 'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255]],
                'extensions' => ['type' => 'array', 'maxItems' => 10, 'items' => ['type' => 'string', 'pattern' => '^[a-z0-9]{1,8}$']],
            ],
        ];
    }

    public function secretFields(): array
    {
        return ['token'];
    }

    /** @return array{host:string,base_url:string,repository:string,branch:string,paths:string[],extensions:string[]} */
    public static function normalise(array $config): array
    {
        $host = (string) ($config['host'] ?? 'github');

        return [
            'host' => $host,
            'base_url' => rtrim((string) ($config['base_url'] ?? self::HOSTS[$host] ?? ''), '/'),
            'repository' => trim((string) ($config['repository'] ?? ''), '/'),
            'branch' => (string) ($config['branch'] ?? 'main'),
            'paths' => array_values((array) ($config['paths'] ?? ['**/*.md'])),
            'extensions' => array_values((array) ($config['extensions'] ?? ['md', 'markdown', 'mdx', 'txt'])),
        ];
    }

    public function validate(array $config, array $secrets): void
    {
        $result = (new Validator())->validate(json_decode((string) json_encode($config)), json_decode((string) json_encode($this->configSchema())));
        if (!$result->isValid()) {
            throw new ConnectorException('The repository settings are not valid: check the host, the repository (owner/name), the branch and the paths.');
        }
        $c = self::normalise($config);
        if ($c['base_url'] === '') {
            throw new ConnectorException('Gitea and Forgejo need the address of the server (base_url).');
        }
        $client = $this->client($c, $secrets['token'] ?? null);
        $head = $client->head();
        $filter = new PathFilter($c['paths'], $c['extensions']);
        $matching = array_filter($client->tree($head), fn ($f) => $filter->accepts($f['path']));
        if ($matching === []) {
            throw new ConnectorException('No file in this branch matches the paths and extensions you chose.');
        }
        $this->assertLimits($matching);
    }

    public function fetch(Connection $connection, ?Revision $latest): FetchResult
    {
        $c = self::normalise((array) $connection->config);
        $client = $this->client($c, ((array) $connection->secrets)['token'] ?? null);
        $head = $client->head();
        if ($latest !== null && $latest->origin_ref === $head) {
            return FetchResult::unchanged($head);
        }
        $filter = new PathFilter($c['paths'], $c['extensions']);
        $tree = array_values(array_filter($client->tree($head), fn ($f) => $filter->accepts($f['path'])));
        if ($tree === []) {
            throw new ConnectorException('No file in this branch matches the paths and extensions you chose.');
        }
        $this->assertLimits($tree);

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
            $bytes ??= $client->blob($entry['blob']);
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
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function verifyWebhook(Connection $connection, Request $request): ?WebhookDelivery
    {
        $c = self::normalise((array) $connection->config);
        $secret = (string) (((array) $connection->secrets)['webhook_secret'] ?? '');
        $body = $request->getContent();
        if ($secret === '' || !$this->signatureMatches($c['host'], $request, $secret, $body)) {
            throw new InvalidSignature('The webhook signature does not match.');
        }
        $event = $request->header('X-GitHub-Event') ?? $request->header('X-Gitea-Event') ?? $request->header('X-Forgejo-Event') ?? $request->header('X-Gitlab-Event') ?? 'push';
        if (!in_array(strtolower((string) $event), ['push', 'push hook'], true)) {
            return null;
        }
        $payload = json_decode($body, true);
        if (!is_array($payload) || ($payload['ref'] ?? null) !== 'refs/heads/' . $c['branch']) {
            return null;
        }
        // the changed files are listed on most hosts; no match with the paths means nothing to do
        $filter = new PathFilter($c['paths'], $c['extensions']);
        $listed = [];
        foreach ((array) ($payload['commits'] ?? []) as $commit) {
            foreach (['added', 'modified', 'removed'] as $kind) {
                foreach ((array) ($commit[$kind] ?? []) as $path) {
                    $listed[] = (string) $path;
                }
            }
        }
        if ($listed !== [] && array_filter($listed, fn ($p) => $filter->accepts($p)) === []) {
            return null;
        }
        $id = $request->header('X-GitHub-Delivery') ?? $request->header('X-Gitea-Delivery') ?? $request->header('X-Forgejo-Delivery') ?? $request->header('X-Gitlab-Event-UUID') ?? ($payload['after'] ?? hash('sha256', $body));

        return new WebhookDelivery(mb_substr((string) $id, 0, 100));
    }

    private function signatureMatches(string $host, Request $request, string $secret, string $body): bool
    {
        $hmac = hash_hmac('sha256', $body, $secret);
        $candidates = [];
        if ($request->hasHeader('X-Ulams-Signature')) {
            $candidates[] = (string) preg_replace('/^sha256=/', '', (string) $request->header('X-Ulams-Signature'));
        }
        match ($host) {
            'github' => $candidates[] = (string) preg_replace('/^sha256=/', '', (string) $request->header('X-Hub-Signature-256')),
            'gitea' => $candidates[] = (string) ($request->header('X-Gitea-Signature') ?? $request->header('X-Forgejo-Signature')),
            default => null,
        };
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && hash_equals($hmac, strtolower($candidate))) {
                return true;
            }
        }
        // GitLab sends the secret itself as a token
        return $host === 'gitlab' && hash_equals($secret, (string) $request->header('X-Gitlab-Token'));
    }

    /** @param array{host:string,base_url:string,repository:string,branch:string,paths:string[],extensions:string[]} $c */
    private function client(array $c, ?string $token): GitHostClient
    {
        $limits = (array) config('living_course.connector_limits');
        $http = $this->http($limits);
        $token = $token !== null && $token !== '' ? $token : null;

        return match ($c['host']) {
            'gitlab' => new GitLabClient($http, $c['base_url'], $c['repository'], $c['branch'], $token),
            'gitea' => new GiteaClient($http, $c['base_url'], $c['repository'], $c['branch'], $token),
            default => new GitHubClient($http, $c['base_url'], $c['repository'], $c['branch'], $token),
        };
    }

    /** @param array<string,mixed> $limits */
    private function http(array $limits): Client
    {
        return SafeHttp::client($this->handler, [
            'max_bytes' => (int) $limits['total_bytes'],
            'allowed_hosts' => (array) config('living_course.allowed_hosts', []),
            'insecure_hosts' => (array) config('living_course.insecure_hosts', []),
            'label' => 'Git hosts',
            'exception' => static fn (string $message) => new ConnectorException($message),
        ]);
    }

    /** @param array<int,array{path:string,blob:string,size:?int}> $files */
    private function assertLimits(array $files): void
    {
        $limits = (array) config('living_course.connector_limits');
        if (count($files) > (int) $limits['files']) {
            throw new ConnectorException(sprintf('%d files match, more than the limit of %d. Narrow the paths (for example docs/**/*.md).', count($files), (int) $limits['files']));
        }
        $total = 0;
        foreach ($files as $f) {
            if ($f['size'] !== null && $f['size'] > (int) $limits['file_bytes']) {
                throw new ConnectorException(sprintf('%s is larger than %d KB, the limit for one source file.', $f['path'], (int) $limits['file_bytes'] / 1024));
            }
            $total += (int) $f['size'];
        }
        if ($total > (int) $limits['total_bytes']) {
            throw new ConnectorException(sprintf('The selected files add up to more than %d MB. Narrow the paths.', (int) $limits['total_bytes'] / 1048576));
        }
    }

    /** The bytes of a file as stored with the previous revision (an unchanged blob is not downloaded again). */
    private function stored(Revision $revision, string $path): ?string
    {
        $disk = Storage::disk(SourceIngestor::disk());
        $file = RevisionService::rawFilePath($revision, $path);

        return $file !== null && $disk->exists($file) ? (string) $disk->get($file) : null;
    }
}
