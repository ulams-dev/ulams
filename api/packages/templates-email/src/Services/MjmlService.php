<?php

namespace Ulams\TemplatesEmail\Services;

use Ulams\TemplatesEmail\UlamsTemplatesEmailServiceProvider;
use Ulams\TemplatesEmail\Mjml\BinaryRenderer;
use Ulams\TemplatesEmail\Services\Contracts\MjmlServiceContract;
use Exception;
use Qferrer\Mjml\Renderer\ApiRenderer;
use Ulams\TemplatesEmail\Services\CurlApi;
use Ulams\TemplatesEmail\Services\CurlRenderer;

class MjmlService implements MjmlServiceContract
{
    public function render(string $mjml): string
    {

        if (config(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.api_url')) {
            $api = new CurlApi(config(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.api_url'));
            $renderer = new CurlRenderer($api);
        } else if (config(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.use_api')) {
            $apiId = config(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.api_id');
            $apiSecret = config(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.api_secret');

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
