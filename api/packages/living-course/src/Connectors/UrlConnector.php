<?php

namespace Ulams\LivingCourse\Connectors;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Opis\JsonSchema\Validator;
use Ulams\Core\Http\SafeHttp;
use Ulams\Core\Http\UnsafeUrlException;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\LivingCourse\Connectors\Url\HtmlConverter;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Services\RevisionService;

/**
 * Up to 20 web pages of one host (documentation sites), checked on a schedule (ADR 0032). Conditional
 * requests (ETag, 304) and a content hash tell a change from no change; HTML is reduced to the main
 * content and converted to Markdown. One file per page; the page path is the first heading element.
 * Redirects are followed up to three hops and never leave the host.
 */
final class UrlConnector implements SourceConnector
{
    /** @param (callable(mixed):mixed)|null $handler Guzzle handler (tests) */
    public function __construct(private readonly mixed $handler = null)
    {
    }

    public function key(): string
    {
        return 'url';
    }

    public function label(): string
    {
        return 'Web pages';
    }

    public function configSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['urls'],
            'properties' => [
                'urls' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 20, 'items' => ['type' => 'string', 'maxLength' => 2000, 'pattern' => '^https://']],
                'selector' => ['type' => 'string', 'maxLength' => 255],
            ],
        ];
    }

    public function secretFields(): array
    {
        return [];
    }

    /** @return array{urls:string[],selector:string} */
    private static function normalise(array $config): array
    {
        return ['urls' => array_values(array_unique(array_map('trim', (array) ($config['urls'] ?? [])))), 'selector' => trim((string) ($config['selector'] ?? '')) ?: HtmlConverter::DEFAULT_SELECTOR];
    }

    public function validate(array $config, array $secrets): void
    {
        $result = (new Validator())->validate(json_decode((string) json_encode($config)), json_decode((string) json_encode($this->configSchema())));
        if (!$result->isValid()) {
            throw new ConnectorException('The page settings are not valid: use 1 to 20 https addresses of one site.');
        }
        $c = self::normalise($config);
        $hosts = array_unique(array_map(fn ($u) => strtolower((string) parse_url($u, PHP_URL_HOST)), $c['urls']));
        if (count($hosts) !== 1 || $hosts[0] === '') {
            throw new ConnectorException('All pages must be on the same site.');
        }
        $this->pages($c, null);
    }

    public function fetch(Connection $connection, ?Revision $latest): FetchResult
    {
        $c = self::normalise((array) $connection->config);
        $pages = $this->pages($c, $latest);
        $ref = hash('sha256', implode("\n", array_map(fn (array $p) => $p['path'] . '=' . $p['hash'], $pages)));
        if ($latest !== null && $latest->origin_ref === $ref) {
            return FetchResult::unchanged($ref);
        }
        $files = array_map(fn (array $p) => new FetchedFile($p['path'], $p['bytes'], $p['kind'], $p['blob']), $pages);

        return new FetchResult(false, $ref, $files, ['fileCount' => count($files), 'host' => parse_url($c['urls'][0], PHP_URL_HOST)]);
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    public function verifyWebhook(Connection $connection, Request $request): ?WebhookDelivery
    {
        return null;
    }

    /**
     * @param array{urls:string[],selector:string} $c
     * @return array<int,array{path:string,bytes:string,kind:string,blob:string,hash:string}>
     */
    private function pages(array $c, ?Revision $latest): array
    {
        $limits = (array) config('living_course.connector_limits');
        $previous = [];
        foreach ((array) ($latest?->metadata['files'] ?? []) as $f) {
            if (isset($f['path'], $f['blob'])) {
                $previous[$f['path']] = $f['blob'];
            }
        }
        $http = $this->http((int) $limits['page_bytes']);
        $pages = [];
        foreach ($c['urls'] as $url) {
            $path = self::pathOf($url);
            $headers = ['User-Agent' => 'ulams-living-course', 'Accept' => 'text/html, text/markdown, text/plain;q=0.8'];
            // the blob of a page is `<sha256 of the body>|<ETag>`: the hash tells a change from none, the ETag asks the server
            [$knownHash, $etag] = isset($previous[$path]) ? array_pad(explode('|', $previous[$path], 2), 2, '') : ['', ''];
            $etag = $etag !== '' ? $etag : null;
            if ($etag !== null) {
                $headers['If-None-Match'] = $etag;
            }
            try {
                $response = $http->get($url, ['headers' => $headers, 'http_errors' => false]);
            } catch (RequestException $e) {
                throw $this->unwrap($e, $url);
            } catch (UnsafeUrlException $e) {
                throw new ConnectorException($e->getMessage(), 0, $e);
            } catch (GuzzleException $e) {
                throw new ConnectorException("{$url} could not be reached.", 0, $e);
            }
            $status = $response->getStatusCode();
            if ($status === 304 && $latest !== null && ($stored = $this->stored($latest, $path)) !== null) {
                $pages[] = ['path' => $path, 'bytes' => $stored['bytes'], 'kind' => $stored['kind'], 'blob' => $previous[$path], 'hash' => $knownHash];
                continue;
            }
            if ($status < 200 || $status >= 300) {
                throw new ConnectorException(match (true) {
                    $status === 404 => "{$url} was not found.",
                    in_array($status, [401, 403], true) => "{$url} needs a login, which source pages cannot use.",
                    default => "{$url} answered {$status}.",
                });
            }
            $type = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));
            $kind = match ($type) {
                'text/html', 'application/xhtml+xml' => 'html',
                'text/markdown', 'text/x-markdown', 'text/plain' => 'markdown',
                default => throw new ConnectorException("{$url} is {$type}, not a web page or a text file."),
            };
            $body = (string) $response->getBody();
            if (strlen($body) > (int) $limits['page_bytes']) {
                throw new ConnectorException(sprintf('%s is larger than %d MB.', $url, (int) $limits['page_bytes'] / 1048576));
            }
            $hash = hash('sha256', $body);
            $tag = $response->getHeaderLine('ETag');
            if ($kind === 'html') {
                $body = $this->html($body, $url, $c['selector']);
                $kind = 'markdown';
            }
            $pages[] = ['path' => $path, 'bytes' => $body, 'kind' => $kind, 'blob' => $hash . '|' . $tag, 'hash' => $hash];
        }

        return $pages;
    }

    private function html(string $html, string $url, string $selector): string
    {
        $converted = (new HtmlConverter())->convert($html, $url, $selector);
        if (trim($converted['markdown']) === '') {
            throw new ConnectorException("No text could be read from {$url}. Check the CSS selector of the main content.");
        }

        return $converted['markdown'];
    }

    private static function pathOf(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    private function http(int $maxBytes): Client
    {
        return SafeHttp::client($this->handler, [
            'max_redirects' => 3,
            'max_bytes' => $maxBytes,
            'allowed_hosts' => (array) config('living_course.allowed_hosts', []),
            'insecure_hosts' => (array) config('living_course.insecure_hosts', []),
            'label' => 'Web pages',
            'exception' => static fn (string $message) => new ConnectorException($message),
        ]);
    }

    private function unwrap(RequestException $e, string $url): ConnectorException
    {
        for ($previous = $e->getPrevious(); $previous !== null; $previous = $previous->getPrevious()) {
            if ($previous instanceof UnsafeUrlException || $previous instanceof ConnectorException) {
                return new ConnectorException($previous->getMessage(), 0, $e);
            }
        }

        return new ConnectorException("{$url} could not be reached.", 0, $e);
    }

    /** @return array{bytes:string,kind:string}|null */
    private function stored(Revision $revision, string $path): ?array
    {
        $file = RevisionService::rawFilePath($revision, $path);
        $disk = Storage::disk(SourceIngestor::disk());

        return $file !== null && $disk->exists($file) ? ['bytes' => (string) $disk->get($file), 'kind' => 'markdown'] : null;
    }
}
