<?php

namespace Ulams\LivingCourse\Connectors\Remote;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Ulams\Core\Http\SafeHttp;
use Ulams\Core\Http\UnsafeUrlException;
use Ulams\LivingCourse\Connectors\ConnectorException;

/**
 * The HTTP side of the connectors that talk to a vendor's API (Google Drive, Notion): every request
 * goes through the SSRF-safe client, tokens live only in request headers, and nothing the remote says is
 * ever copied into a message: errors are mapped to our own words by status.
 */
final class RemoteApi
{
    private Client $http;

    /**
     * @param (callable(mixed):mixed)|null $handler Guzzle handler (tests)
     * @param string $label what the author calls it: "Google Drive"
     */
    public function __construct(?callable $handler, private readonly string $label, int $maxBytes)
    {
        $this->http = SafeHttp::client($handler, [
            'max_bytes' => $maxBytes,
            'allowed_hosts' => (array) config('living_course.allowed_hosts', []),
            'insecure_hosts' => (array) config('living_course.insecure_hosts', []),
            'label' => $label,
            'exception' => static fn (string $message) => new ConnectorException($message),
        ]);
    }

    /**
     * @param array<string,mixed> $options Guzzle options (query, headers, form_params, json, auth)
     * @param array<int,string> $errors status => our message, for the statuses worth explaining
     */
    public function request(string $method, string $url, array $options, array $errors = []): ResponseInterface
    {
        try {
            $response = $this->http->request($method, $url, $options + ['http_errors' => false, 'headers' => []]);
        } catch (RequestException $e) {
            throw $this->unwrap($e);
        } catch (UnsafeUrlException $e) {
            throw new ConnectorException($e->getMessage(), 0, $e);
        } catch (GuzzleException $e) {
            throw new ConnectorException("{$this->label} could not be reached. Try again later.", 0, $e);
        }
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return $response;
        }
        throw new ConnectorException($errors[$status] ?? match (true) {
            $status === 429 => "{$this->label} is limiting requests. The next check tries again.",
            $status >= 500 => "{$this->label} is not available right now. The next check tries again.",
            default => "{$this->label} answered {$status}.",
        });
    }

    /** @return array<string,mixed> */
    public function json(string $method, string $url, array $options, array $errors = []): array
    {
        $data = json_decode((string) $this->request($method, $url, $options, $errors)->getBody(), true);
        if (!is_array($data)) {
            throw new ConnectorException("{$this->label} sent an answer we cannot read.");
        }

        return $data;
    }

    private function unwrap(RequestException $e): ConnectorException
    {
        for ($previous = $e->getPrevious(); $previous !== null; $previous = $previous->getPrevious()) {
            if ($previous instanceof UnsafeUrlException || $previous instanceof ConnectorException) {
                return new ConnectorException($previous->getMessage(), 0, $e);
            }
        }

        return new ConnectorException("{$this->label} could not be reached. Try again later.", 0, $e);
    }
}
