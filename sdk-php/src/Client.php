<?php

namespace Ulams\Sdk;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;

/**
 * Entry point of the SDK: one HTTP client and one Configuration shared by every generated API class.
 *
 *     $ulams = Client::make('https://academy.example.com', 'token');
 *     $courses = $ulams->api(Api\AdminCoursesApi::class);
 *
 * Authentication is a bearer token (a scoped personal access token, ADR 0074). Every request that changes data
 * carries an `Idempotency-Key` (a retry of the same request object reuses it) and every request an
 * `X-Request-Id`, both unless you set them yourself.
 */
final class Client
{
    private readonly Configuration $configuration;

    private readonly ClientInterface $http;

    /**
     * @param array<string,mixed> $guzzle options for the Guzzle client (timeouts, proxy); a `handler` that is a HandlerStack gets the request-id middleware pushed onto it; any other handler is used as is
     */
    public function __construct(string $baseUrl, ?string $token = null, ?ClientInterface $http = null, array $guzzle = [])
    {
        $this->configuration = (new Configuration())->setHost(rtrim($baseUrl, '/'));
        if ($token !== null && $token !== '') {
            $this->configuration->setAccessToken($token);
        }
        if ($http === null) {
            $stack = $guzzle['handler'] ?? HandlerStack::create();
            if ($stack instanceof HandlerStack) {
                $stack->push(RequestIds::middleware(), 'ulams_request_ids');
            }
            $http = new GuzzleClient($guzzle + ['timeout' => 30] + ['handler' => $stack]);
        }
        $this->http = $http;
    }

    public static function make(string $baseUrl, ?string $token = null, ?ClientInterface $http = null, array $guzzle = []): self
    {
        return new self($baseUrl, $token, $http, $guzzle);
    }

    public function configuration(): Configuration
    {
        return $this->configuration;
    }

    public function http(): ClientInterface
    {
        return $this->http;
    }

    /**
     * An API class of the generated client, wired to this client.
     *
     * @template T of object
     * @param class-string<T> $api for example `Api\AdminCoursesApi::class`
     * @return T
     */
    public function api(string $api): object
    {
        return new $api($this->http, $this->configuration);
    }
}
