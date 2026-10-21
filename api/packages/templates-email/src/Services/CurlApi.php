<?php

namespace Ulams\TemplatesEmail\Services;

use Illuminate\Support\Facades\Log;
use Qferrer\Mjml\ApiInterface;
use Qferrer\Mjml\Exception\ApiException;
use Qferrer\Mjml\Http\CurlInterface;
use Qferrer\Mjml\Http\Curl;

/**
 * @see https://mjml.io/api/documentation/
 */
final class CurlApi implements ApiInterface
{
    protected $apiEndpoint = "https://api.mjml.io/v1";
    protected $curl;
    private int $connectTimeout;
    private int $timeout;

    public function __construct(
        string $apiEndpoint = "https://api.mjml.io/v1",
        ?CurlInterface $curl = null,
        int $connectTimeout = 5,
        int $timeout = 20
    ) {
        $this->apiEndpoint = $apiEndpoint;
        $this->curl = $curl ?? new Curl();
        $this->connectTimeout = max(1, $connectTimeout);
        $this->timeout = max(1, $timeout);
    }

    public function getHtml(string $mjml): string
    {
        $data = $this->getResult($mjml);

        return $data['html'] ?? '';
    }

    public function getMjmlVersion(): string
    {
        $data = $this->getResult('<mjml></mjml>');

        return $data['mjml_version'] ?? 'unknown';
    }

    private function getResult(string $mjml): array
    {
        $response = $this->curl->request($this->apiEndpoint . '/render', [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "UTF-8",
            // The renderer is an internal service: never follow a redirect to somewhere else, and
            // never wait forever (this runs while an e-mail template is saved or sent).
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['mjml' => $mjml]),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
            ]
        ]);

        $data = @json_decode((string) $response->getContent(), true);

        if (!is_array($data)) {
            throw new ApiException(sprintf(
                'Unable to decode the JSON response: "%s".',
                json_last_error_msg()
            ));
        }

        $httpCode = $response->getStatusCode();

        if ($httpCode !== 200) {
            $errors = $this->describeErrors($data['errors'] ?? []);
            throw new ApiException(sprintf(
                'Unexpected HTTP code: %s. Api Error Message: "%s".%s',
                $httpCode,
                $data['message'] ?? 'Unknown Error',
                $errors === [] ? '' : ' MJML errors: ' . implode('; ', $errors)
            ));
        }

        $errors = $this->describeErrors($data['errors'] ?? []);
        if ($errors !== []) {
            // Soft validation: the HTML is still returned, but the author must be able to find out why
            // it may look wrong. Only the MJML error list is logged, never the template or the request.
            Log::warning('The MJML renderer reported errors.', ['errors' => $errors]);
        }

        return $data;
    }

    /**
     * @param mixed $errors the `errors` list of the MJML response ({line, message, tagName, formattedMessage})
     * @return string[] at most 10 short, single-line descriptions
     */
    private function describeErrors($errors): array
    {
        if (!is_array($errors)) {
            return [];
        }

        $result = [];
        foreach (array_slice($errors, 0, 10) as $error) {
            if (is_array($error)) {
                $message = (string) ($error['message'] ?? $error['formattedMessage'] ?? '');
                $line = isset($error['line']) ? 'line ' . $error['line'] . ': ' : '';
                $tag = isset($error['tagName']) ? ' (' . $error['tagName'] . ')' : '';
                $text = $line . $message . $tag;
            } else {
                $text = (string) $error;
            }
            $text = trim(preg_replace('/\s+/', ' ', $text));
            if ($text !== '') {
                $result[] = mb_substr($text, 0, 200);
            }
        }

        return $result;
    }
}
