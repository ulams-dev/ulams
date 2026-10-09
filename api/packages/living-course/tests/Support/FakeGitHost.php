<?php

namespace Ulams\LivingCourse\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * A fake GitHub/GitLab/Gitea for connector tests: the host's REST API over an in-memory repository
 * (path => text), served through a Guzzle mock handler that records every request.
 */
final class FakeGitHost
{
    /** @var array<string,string> path => contents */
    public array $files = [];

    /** @var RequestInterface[] */
    public array $requests = [];

    /** @var array<int,int> status codes to answer the next requests with (consumed first) */
    public array $failWith = [];

    public function __construct(public string $host = 'github', public string $branch = 'main')
    {
    }

    public function head(): string
    {
        ksort($this->files);

        return sha1($this->branch . json_encode($this->files));
    }

    public static function blobId(string $text): string
    {
        return sha1("blob\0" . $text);
    }

    /** The Guzzle handler: every request is recorded and routed by its URL. */
    public function handler(): \Closure
    {
        return function (RequestInterface $request, array $options) {
            $this->requests[] = $request;

            return \GuzzleHttp\Promise\Create::promiseFor($this->respond($request));
        };
    }

    public function respond(RequestInterface $request): Response
    {
        if ($this->failWith !== []) {
            return new Response(array_shift($this->failWith), [], '{"message":"failed"}');
        }
        $path = $request->getUri()->getPath();
        $json = fn (array $data) => new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($data));

        if ($this->host === 'github') {
            if (preg_match('~/commits/main$~', $path)) {
                return new Response(200, [], $this->head());
            }
            if (str_contains($path, '/git/trees/')) {
                return $json(['sha' => $this->head(), 'truncated' => false, 'tree' => $this->tree()]);
            }
            if (preg_match('~/git/blobs/([0-9a-f]+)$~', $path, $m)) {
                return $this->blob($m[1], $json);
            }
        }
        if ($this->host === 'gitea') {
            if (preg_match('~/branches/main$~', $path)) {
                return $json(['name' => 'main', 'commit' => ['id' => $this->head()]]);
            }
            if (str_contains($path, '/git/trees/')) {
                return $json(['sha' => $this->head(), 'truncated' => false, 'tree' => $this->tree()]);
            }
            if (preg_match('~/git/blobs/([0-9a-f]+)$~', $path, $m)) {
                return $this->blob($m[1], $json);
            }
        }
        if ($this->host === 'gitlab') {
            if (preg_match('~/repository/branches/main$~', $path)) {
                return $json(['name' => 'main', 'commit' => ['id' => $this->head()]]);
            }
            if (str_ends_with($path, '/repository/tree')) {
                parse_str($request->getUri()->getQuery(), $q);

                return $json(($q['page'] ?? '1') === '1' ? array_map(fn ($e) => ['id' => $e['sha'], 'name' => basename($e['path']), 'type' => 'blob', 'path' => $e['path']], $this->tree()) : []);
            }
            if (preg_match('~/repository/blobs/([0-9a-f]+)/raw$~', $path, $m)) {
                foreach ($this->files as $text) {
                    if (self::blobId($text) === $m[1]) {
                        return new Response(200, [], $text);
                    }
                }
            }
        }

        return new Response(404, [], '{"message":"Not Found"}');
    }

    /** @return array<int,array{path:string,type:string,sha:string,size:int}> */
    private function tree(): array
    {
        ksort($this->files);

        return array_map(fn ($path, $text) => ['path' => $path, 'type' => 'blob', 'sha' => self::blobId($text), 'size' => strlen($text)], array_keys($this->files), $this->files);
    }

    private function blob(string $id, \Closure $json): Response
    {
        foreach ($this->files as $text) {
            if (self::blobId($text) === $id) {
                return $json(['content' => chunk_split(base64_encode($text)), 'encoding' => 'base64', 'size' => strlen($text)]);
            }
        }

        return new Response(404, [], '{"message":"Not Found"}');
    }

    /** Number of recorded requests whose path matches. */
    public function count(string $pattern): int
    {
        return count(array_filter($this->requests, fn (RequestInterface $r) => (bool) preg_match($pattern, $r->getUri()->getPath())));
    }
}
