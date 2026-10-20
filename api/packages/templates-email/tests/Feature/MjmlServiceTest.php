<?php

namespace Ulams\TemplatesEmail\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Ulams\TemplatesEmail\Mjml\BinaryRenderer;
use Ulams\TemplatesEmail\Mjml\PlainTextRenderer;
use Ulams\TemplatesEmail\Services\CurlRenderer;
use Ulams\TemplatesEmail\Services\MjmlService;
use Ulams\TemplatesEmail\Tests\TestCase;
use Ulams\TemplatesEmail\UlamsTemplatesEmailServiceProvider;

class MjmlServiceTest extends TestCase
{
    private const MJML = '<mjml><mj-head><mj-style>.a{color:red}</mj-style></mj-head><mj-body><mj-section><mj-column>'
        . '<mj-text>Hello <b>Ada</b> &amp; welcome</mj-text>'
        . '<mj-button href="https://app.example.org/verify?x=1&amp;y=2">Verify e-mail</mj-button>'
        . '</mj-column></mj-section></mj-body></mjml>';

    protected function setUp(): void
    {
        parent::setUp();
        $warned = new \ReflectionProperty(MjmlService::class, 'warned');
        $warned->setValue(null, false);
    }

    private function configure(?string $apiUrl, ?string $binary): void
    {
        Config::set(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.api_url', $apiUrl);
        Config::set(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.binary_path', $binary);
    }

    public function testTheHttpServiceIsUsedWhenItsUrlIsSet(): void
    {
        $this->configure('http://mjml:15500/v1', '/usr/bin/mjml');

        $this->assertInstanceOf(CurlRenderer::class, (new MjmlService())->renderer());
    }

    public function testAnExecutableBinaryIsUsedWithoutAnUrl(): void
    {
        $this->configure(null, PHP_BINARY);

        $this->assertInstanceOf(BinaryRenderer::class, (new MjmlService())->renderer());
    }

    public function testWithoutAnyRendererTheTextIsKeptAndAWarningIsLoggedOnce(): void
    {
        $this->configure(null, '/usr/bin/mjml-that-does-not-exist');
        Log::shouldReceive('warning')->once()->withArgs(fn (string $message) => str_contains($message, 'MJML_API_URL'));
        $service = new MjmlService();

        $html = $service->render(self::MJML);
        $again = $service->render(self::MJML);

        $this->assertInstanceOf(PlainTextRenderer::class, $service->renderer());
        $this->assertSame($html, $again);
        $this->assertStringContainsString('<p>Hello Ada &amp; welcome</p>', $html);
        $this->assertStringContainsString('Verify e-mail (https://app.example.org/verify?x=1&amp;y=2)', $html);
        $this->assertStringNotContainsString('color:red', $html);
        $this->assertStringNotContainsString('<mj-', $html);
    }

    public function testMarkupInTheTemplateIsNeverPassedThrough(): void
    {
        $this->configure(null, null);
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $html = (new MjmlService())->render('<mjml><mj-body><mj-text>&lt;script&gt;alert(1)&lt;/script&gt;</mj-text></mj-body></mjml>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }
}
