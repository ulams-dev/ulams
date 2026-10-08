<?php

namespace EscolaLms\TemplatesEmail\Services;

use EscolaLms\TemplatesEmail\EscolaLmsTemplatesEmailServiceProvider;
use EscolaLms\TemplatesEmail\Mjml\BinaryRenderer;
use EscolaLms\TemplatesEmail\Services\Contracts\MjmlServiceContract;
use Exception;
use Qferrer\Mjml\Renderer\ApiRenderer;
use EscolaLms\TemplatesEmail\Services\CurlApi;
use EscolaLms\TemplatesEmail\Services\CurlRenderer;

class MjmlService implements MjmlServiceContract
{
    public function render(string $mjml): string
    {

        if (config(EscolaLmsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.api_url')) {
            $api = new CurlApi(config(EscolaLmsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.api_url'));
            $renderer = new CurlRenderer($api);
        } else if (config(EscolaLmsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.use_api')) {
            $apiId = config(EscolaLmsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.api_id');
            $apiSecret = config(EscolaLmsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.api_secret');

            if (empty($apiId) || empty($apiSecret)) {
                throw new Exception('Missing MJML API id and/or secret');
            }

            $renderer = new ApiRenderer($apiId);
        } else {
            $renderer = new BinaryRenderer();
        }
        return $renderer->render($mjml);
    }
}
