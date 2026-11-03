<?php

namespace Ulams\TemplatesEmail\Mjml;

use Qferrer\Mjml\Process\Process;
use Qferrer\Mjml\Renderer\BinaryRenderer as QferrerBinaryRenderer;
use Ulams\TemplatesEmail\UlamsTemplatesEmailServiceProvider;

class BinaryRenderer extends QferrerBinaryRenderer
{
    private string $command;

    public function __construct()
    {
        $bin = $this->getMjmlBinaryPath();
        parent::__construct($bin);

        // Same flags as the parent, plus --config.ignoreIncludes: a template author must not be able to
        // read local files with <mj-include path="/etc/passwd" /> (the mjml CLI includes them by default).
        $this->command = escapeshellarg($bin) . ' -i -s --config.validationLevel --config.minify --config.ignoreIncludes';
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function render(string $content): string
    {
        $process = new Process($this->command, $content);
        $process->run();

        return $process->getOutput();
    }

    public function getMjmlBinaryPath(): string
    {
        return config(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.binary_path', base_path('node_modules/.bin/mjml')) ?? base_path('node_modules/.bin/mjml');
    }
}
