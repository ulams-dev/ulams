<?php

namespace Ulams\LivingCourse\Tests\Support;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * A scripted vendor API for connector tests (Google Drive, Notion, their token endpoints): routes are
 * `METHOD /regex/` against the full URL with its query, answered by a closure or a fixed body; every
 * request is recorded. Nothing leaves the process.
 */
final class FakeVendor
{
    /** @var RequestInterface[] */
    public array $requests = [];

    /** @var string[] urls nothing answered (a 404 for the connector, a hint for the test) */
    public array $missed = [];

    /** @var array<int,array{0:string,1:string,2:callable}> */
    private array $routes = [];

    /** @param array<string,mixed>|string $body */
    public function on(string $method, string $pattern, array|string|callable $body, int $status = 200): self
    {
        $answer = is_callable($body) ? $body : fn (RequestInterface $r) => new Response($status, is_array($body) ? ['Content-Type' => 'application/json'] : [], is_array($body) ? (string) json_encode($body) : $body);
        array_unshift($this->routes, [$method, $pattern, $answer]);

        return $this;
    }

    public function handler(): \Closure
    {
        return function (RequestInterface $request, array $options) {
            $this->requests[] = $request;
            $url = rawurldecode((string) $request->getUri());
            foreach ($this->routes as [$method, $pattern, $answer]) {
                if ($method === $request->getMethod() && preg_match($pattern, $url)) {
                    return Create::promiseFor($answer($request));
                }
            }

            $this->missed[] = $request->getMethod() . ' ' . $url;

            return Create::promiseFor(new Response(404, ['Content-Type' => 'application/json'], '{"error":"not found"}'));
        };
    }

    /** @return RequestInterface[] */
    public function matching(string $pattern): array
    {
        return array_values(array_filter($this->requests, fn (RequestInterface $r) => preg_match($pattern, rawurldecode((string) $r->getUri())) === 1));
    }

    public function count(string $pattern): int
    {
        return count($this->matching($pattern));
    }
}
