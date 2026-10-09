<?php

namespace Ulams\TemplatesPdf\Tests\Unit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Ulams\TemplatesPdf\Exceptions\PdfRenderException;
use Ulams\TemplatesPdf\Services\Contracts\PdfRendererContract;
use Ulams\TemplatesPdf\Tests\TestCase;

class PdfServiceRendererTest extends TestCase
{
    private array $template = ['basePdf' => ['width' => 210, 'height' => 297, 'padding' => [0, 0, 0, 0]], 'schemas' => [[]]];

    public function testPostsTemplateAndInputsWithTheInternalToken(): void
    {
        Http::fake([self::PDF_SERVICE . '/render' => Http::response('%PDF-1.7 ok', 200, ['Content-Type' => 'application/pdf'])]);

        $pdf = app(PdfRendererContract::class)->render($this->template, ['x' => ['a' => 'b']]);

        $this->assertSame('%PDF-1.7 ok', $pdf);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === self::PDF_SERVICE . '/render'
            && $request->hasHeader('X-Internal-Token', self::PDF_TOKEN)
            && $request->data() === ['template' => $this->template, 'inputs' => [['a' => 'b']]]);
    }

    public function testServiceErrorsBecomeExceptionsWithStatusAndCode(): void
    {
        Http::fake([self::PDF_SERVICE . '/render' => Http::response(['error' => 'missing_required_fields', 'message' => 'required fields have no value', 'details' => ['@VarUserName']], 422)]);

        try {
            app(PdfRendererContract::class)->render($this->template, [[]]);
            $this->fail('expected an exception');
        } catch (PdfRenderException $e) {
            $this->assertSame(422, $e->getStatus());
            $this->assertSame('missing_required_fields', $e->getErrorCode());
            $this->assertSame(['@VarUserName'], $e->getDetails());
        }
    }

    public function testNonPdfBodyAndUnreachableServiceAreErrors(): void
    {
        $calls = 0;
        Http::fake([self::PDF_SERVICE . '/render' => function () use (&$calls) {
            if ($calls++ === 0) {
                return Http::response('<html>proxy error</html>', 200);
            }
            throw new ConnectionException('Connection refused');
        }]);
        $renderer = app(PdfRendererContract::class);

        try {
            $renderer->render($this->template, [[]]);
            $this->fail('expected an exception');
        } catch (PdfRenderException $e) {
            $this->assertStringContainsString('not a PDF', $e->getMessage());
        }

        try {
            $renderer->render($this->template, [[]]);
            $this->fail('expected an exception');
        } catch (PdfRenderException $e) {
            $this->assertSame('service_unreachable', $e->getErrorCode());
            $this->assertSame(503, $e->getStatus());
        }
    }
}
