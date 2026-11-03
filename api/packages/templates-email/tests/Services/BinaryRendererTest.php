<?php

namespace Ulams\TemplatesEmail\Tests\Services;

use Ulams\TemplatesEmail\Mjml\BinaryRenderer;
use Ulams\TemplatesEmail\Tests\TestCase;
use Ulams\TemplatesEmail\UlamsTemplatesEmailServiceProvider;

class BinaryRendererTest extends TestCase
{
    private const INCLUDE_TEMPLATE = '<mjml><mj-body><mj-include path="/etc/passwd" /></mj-body></mjml>';

    private ?string $dir = null;

    protected function tearDown(): void
    {
        if ($this->dir !== null) {
            array_map('unlink', glob($this->dir . '/*') ?: []);
            @rmdir($this->dir);
        }
        parent::tearDown();
    }

    private function fakeBinary(): string
    {
        $this->dir = sys_get_temp_dir() . '/mjml-bin-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $bin = $this->dir . '/mjml';
        file_put_contents($bin, "#!/bin/sh\nprintf '%s\\n' \"\$@\" > \"$this->dir/args\"\ncat > \"$this->dir/stdin\"\necho '<html>ok</html>'\n");
        chmod($bin, 0755);

        return $bin;
    }

    public function testCommandIgnoresIncludes(): void
    {
        config([UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.binary_path' => '/usr/local/bin/mjml']);

        $command = (new BinaryRenderer())->getCommand();

        $this->assertStringContainsString('--config.ignoreIncludes', $command);
        $this->assertStringContainsString('-i -s', $command);
    }

    public function testBinaryIsCalledWithIgnoreIncludes(): void
    {
        config([UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.binary_path' => $this->fakeBinary()]);

        $html = (new BinaryRenderer())->render(self::INCLUDE_TEMPLATE);

        $this->assertStringContainsString('ok', $html);
        $args = file_get_contents($this->dir . '/args');
        $this->assertStringContainsString('--config.ignoreIncludes', $args);
        $this->assertSame(self::INCLUDE_TEMPLATE, file_get_contents($this->dir . '/stdin'));
    }

    public function testRealMjmlDoesNotReadLocalFiles(): void
    {
        $bin = trim((string) shell_exec('command -v mjml 2>/dev/null'));
        if ($bin === '') {
            $this->markTestSkipped('The mjml binary is not installed.');
        }
        config([UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.binary_path' => $bin]);

        $html = (new BinaryRenderer())->render(self::INCLUDE_TEMPLATE);

        $this->assertStringNotContainsString('root:', $html);
    }
}
