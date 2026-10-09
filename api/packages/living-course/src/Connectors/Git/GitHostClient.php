<?php

namespace Ulams\LivingCourse\Connectors\Git;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Ulams\Core\Http\UnsafeUrlException;
use Ulams\LivingCourse\Connectors\ConnectorException;

/**
 * A Git host's REST API (ADR 0032): the head commit of a branch, its file tree and file contents.
 * No `git` binary, no clone: only the files that changed are downloaded. Tokens stay in the
 * headers of these requests and never appear in messages or logs.
 */
abstract class GitHostClient
{
    public function __construct(
        protected readonly Client $http,
        protected readonly string $baseUrl,
        protected readonly string $repository,
        protected readonly string $branch,
        protected readonly ?string $token = null,
    ) {
    }

    /** @return string the SHA of the head commit of the branch */
    abstract public function head(): string;

    /** @return array<int,array{path:string,blob:string,size:?int}> every file of the commit, in path order */
    abstract public function tree(string $commit): array;

    abstract public function blob(string $blob): string;

    /** @return array<string,string> */
    protected function headers(): array
    {
        return ['User-Agent' => 'ulams-living-course', 'Accept' => 'application/json'];
    }

    /** @param array<string,mixed> $query @param array<string,string> $headers */
    protected function get(string $url, array $query = [], array $headers = []): ResponseInterface
    {
        try {
            return $this->http->get($url, ['query' => $query, 'headers' => $headers + $this->headers(), 'http_errors' => false]);
        } catch (RequestException $e) {
            throw $this->unwrap($e);
        } catch (UnsafeUrlException $e) {
            throw new ConnectorException($e->getMessage(), 0, $e);
        } catch (GuzzleException $e) {
            throw new ConnectorException('The Git host could not be reached. Check the address and try again.', 0, $e);
        }
    }

    private function unwrap(RequestException $e): ConnectorException
    {
        for ($previous = $e->getPrevious(); $previous !== null; $previous = $previous->getPrevious()) {
            if ($previous instanceof UnsafeUrlException || $previous instanceof ConnectorException) {
                return new ConnectorException($previous->getMessage(), 0, $e);
            }
        }

        return new ConnectorException('The Git host could not be reached. Check the address and try again.', 0, $e);
    }

    /** @return array<string,mixed> */
    protected function json(ResponseInterface $response, string $what): array
    {
        $this->assertOk($response, $what);
        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data)) {
            throw new ConnectorException("The Git host sent an answer we cannot read ({$what}).");
        }

        return $data;
    }

    protected function assertOk(ResponseInterface $response, string $what): void
    {
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return;
        }
        $limited = $status === 429 || ($status === 403 && $response->getHeaderLine('X-RateLimit-Remaining') === '0');
        throw new ConnectorException(match (true) {
            $limited => 'The Git host rate limit is reached' . ($this->token === null ? '; without a token GitHub allows only 60 requests an hour. Add a read-only token.' : '. Try again later.'),
            $status === 401 => 'The Git host refused the token. Check that it is valid and not expired.',
            $status === 403 => 'The token has no access to this repository. It needs read access to its contents.',
            $status === 404 => $this->token === null ? 'The repository or branch was not found. For a private repository add a read-only token.' : 'The repository or branch was not found, or the token cannot see it.',
            default => "The Git host answered {$status} ({$what}).",
        });
    }

    protected function content(array $blob): string
    {
        $content = (string) ($blob['content'] ?? '');
        if (($blob['encoding'] ?? 'base64') !== 'base64') {
            return $content;
        }
        $decoded = base64_decode(str_replace(["\n", "\r"], '', $content), true);
        if ($decoded === false) {
            throw new ConnectorException('The Git host sent a file we cannot decode.');
        }

        return $decoded;
    }
}
