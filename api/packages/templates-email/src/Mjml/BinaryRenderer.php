<?php

namespace Ulams\TemplatesEmail\Mjml;

use Ulams\TemplatesEmail\UlamsTemplatesEmailServiceProvider;
use Qferrer\Mjml\Renderer\BinaryRenderer as QferrerBinaryRenderer;

class BinaryRenderer extends QferrerBinaryRenderer
{
    public function __construct()
    {
        parent::__construct($this->getMjmlBinaryPath());
    }

    public function getMjmlBinaryPath(): string
    {
        return config(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.binary_path', base_path('node_modules/.bin/mjml')) ?? base_path('node_modules/.bin/mjml');
    }
}
