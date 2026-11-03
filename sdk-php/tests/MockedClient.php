<?php

namespace Ulams\Sdk\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Ulams\Sdk\Client;

/** A Client whose HTTP layer is a queue of canned answers; the requests it made are in `$history`. */
final class MockedClient
{
    /** @var list<array{request:\Psr\Http\Message\RequestInterface}> */
    public array $history = [];

    public readonly Client $client;

    /** @param list<Response> $answers */
    public function __construct(array $answers, ?string $token = 'secret-token')
    {
        $stack = HandlerStack::create(new MockHandler($answers));
        $this->client = Client::make('https://academy.test/', $token, null, ['handler' => $stack]);
        // pushed after the client's own middleware, so the history sees the request as it goes out
        $stack->push(Middleware::history($this->history), 'history');
    }

    public static function json(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function request(int $index = 0): \Psr\Http\Message\RequestInterface
    {
        return $this->history[$index]['request'];
    }
}
