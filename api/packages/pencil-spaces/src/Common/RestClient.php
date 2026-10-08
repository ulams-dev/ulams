<?php

namespace EscolaLms\PencilSpaces\Common;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class RestClient
{
    public PendingRequest $httpClient;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->httpClient = Http::withToken($apiKey)->baseUrl($baseUrl);
    }

    /**
     * @throws RequestException
     */
    public function get(string $url, array $params = []): Response
    {
        return $this->httpClient
            ->get($url, $params)
            ->throw();
    }

    /**
     * @throws RequestException
     */
    public function post(string $url, array $data = []): Response
    {
        return $this->httpClient
            ->post($url, $data)
            ->throw();
    }
}
