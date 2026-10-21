<?php

namespace Ulams\TemplatesEmail\Services;

use Qferrer\Mjml\ApiInterface;
use Qferrer\Mjml\RendererInterface;

/**
 * Class ApiRenderer
 */
class CurlRenderer implements RendererInterface
{
    /**
     * The HTTP client.
     *
     * @var ApiInterface
     */
    private $client;

    /**
     * ApiRenderer constructor.
     *
     * @param ApiInterface $httpClient
     */
    public function __construct(ApiInterface $httpClient)
    {
        $this->client = $httpClient;
    }

    /**
     * @return array{html: string, errors: string[]}
     */
    public function renderWithErrors(string $content): array
    {
        if ($this->client instanceof CurlApi) {
            return $this->client->renderWithErrors($content);
        }

        return ['html' => $this->client->getHtml($content), 'errors' => []];
    }

    /**
     * @inheritDoc
     */
    public function render(string $content): string
    {
        return $this->client->getHtml($content);
    }
}
