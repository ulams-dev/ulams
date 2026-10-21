<?php

namespace Ulams\TemplatesEmail\Services;

use Illuminate\Support\Facades\Log;
use Qferrer\Mjml\RendererInterface;
use Ulams\TemplatesEmail\Mjml\BinaryRenderer;
use Ulams\TemplatesEmail\Mjml\PlainTextRenderer;
use Ulams\TemplatesEmail\Services\Contracts\MjmlServiceContract;
use Ulams\TemplatesEmail\UlamsTemplatesEmailServiceProvider;

class MjmlService implements MjmlServiceContract
{
    private static bool $warned = false;

    public function render(string $mjml): string
    {
        return $this->renderer()->render($mjml);
    }

    /**
     * The MJML HTTP service (MJML_API_URL) wins; a local mjml binary (MJML_BINARY_PATH) is used only when the
     * file is executable; otherwise the text of the template is sent without layout and a warning is logged once.
     */
    public function renderer(): RendererInterface
    {
        $apiUrl = config(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.api_url');
        if (!empty($apiUrl)) {
            return new CurlRenderer(new CurlApi(
                $apiUrl,
                null,
                (int) config(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.connect_timeout', 5),
                (int) config(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.timeout', 20)
            ));
        }

        $binary = config(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.binary_path');
        if (!empty($binary) && is_executable($binary)) {
            return new BinaryRenderer();
        }

        if (!self::$warned) {
            self::$warned = true;
            Log::warning('No MJML renderer is configured (set MJML_API_URL, e.g. http://mjml:15500/v1); e-mail templates are rendered as plain text.');
        }

        return new PlainTextRenderer();
    }
}
