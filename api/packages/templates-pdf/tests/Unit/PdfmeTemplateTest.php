<?php

namespace Ulams\TemplatesPdf\Tests\Unit;

use Ulams\TemplatesPdf\Exceptions\LegacyTemplateException;
use Ulams\TemplatesPdf\Exceptions\PdfRenderException;
use Ulams\TemplatesPdf\Pdfme\CertificateTemplates;
use Ulams\TemplatesPdf\Pdfme\PdfmeTemplate;
use Ulams\TemplatesPdf\Tests\TestCase;

class PdfmeTemplateTest extends TestCase
{
    private function template(array $fields): array
    {
        return ['basePdf' => ['width' => 210, 'height' => 297, 'padding' => [0, 0, 0, 0]], 'schemas' => [$fields]];
    }

    private function field(string $name, string $content = '', bool $readOnly = false): array
    {
        return array_filter([
            'name' => $name, 'type' => 'text', 'content' => $content,
            'position' => ['x' => 0, 'y' => 0], 'width' => 10, 'height' => 10,
            'readOnly' => $readOnly ?: null,
        ], fn ($v) => $v !== null);
    }

    public function testDecodesJsonStringsArraysAndDoubleEncodedJson(): void
    {
        $template = $this->template([]);
        $json = json_encode($template);

        $this->assertEquals($template, PdfmeTemplate::decode($json));
        $this->assertEquals($template, PdfmeTemplate::decode($template));
        $this->assertEquals($template, PdfmeTemplate::decode(json_encode($json)));

        $this->expectException(PdfRenderException::class);
        PdfmeTemplate::decode('not json');
    }

    public function testMapsVariablesToFieldsAndReadOnlyText(): void
    {
        $template = $this->template([
            $this->field('@VarUserName', 'sample name'),
            $this->field('${VarCourseTitle}', 'sample title'),
            $this->field('VarToday', 'sample date'),
            $this->field('note', 'Static note for @VarUserName'),
            $this->field('@VarMissing', 'sample'),
            $this->field('footer', 'Issued by @VarAppName on ${VarToday}', true),
        ]);

        $prepared = PdfmeTemplate::prepare($template, [
            '@VarUserName' => 'Zażółć "Gęślą"',
            '@VarCourseTitle' => 'Kurs',
            '@VarToday' => '08.10.2026',
            '@VarAppName' => 'Ulams',
            '@VarMissing' => null,
        ]);

        $this->assertSame([[
            '@VarUserName' => 'Zażółć "Gęślą"',
            '${VarCourseTitle}' => 'Kurs',
            'VarToday' => '08.10.2026',
            // fields not named after a variable keep their content (variables replaced)
            'note' => 'Static note for Zażółć "Gęślą"',
            // a variable without a value renders empty, not the designer sample
            '@VarMissing' => '',
        ]], $prepared['inputs']);
        $this->assertSame('Issued by Ulams on 08.10.2026', $prepared['template']['schemas'][0][5]['content']);
    }

    public function testDefaultCertificateUsesTheCourseVariables(): void
    {
        $template = CertificateTemplates::template();
        $names = PdfmeTemplate::fieldNames($template);

        foreach (['@VarUserName', '@VarCourseTitle', '@VarToday', '@VarCertificateId', '@VarCertificateVerifyUrl', '@VarAppName'] as $var) {
            $this->assertContains($var, $names);
        }
        $this->assertSame(297, $template['basePdf']['width']);
        $this->assertSame(210, $template['basePdf']['height']);
        $this->assertSame('qrcode', collect(PdfmeTemplate::fields($template))->firstWhere('name', '@VarCertificateVerifyUrl')['type']);

        foreach (CertificateTemplates::THEMES as $theme) {
            $this->assertEmpty(PdfmeTemplate::unusedVariables(CertificateTemplates::template($theme), ['@VarUserName', '@VarCourseTitle', '@VarCertificateVerifyUrl']), $theme);
        }
    }

    public function testRefusesReportBroAndUnknownContent(): void
    {
        try {
            PdfmeTemplate::prepare(['docElements' => [], 'documentProperties' => [], 'parameters' => []], []);
            $this->fail('ReportBro content must not be rendered');
        } catch (LegacyTemplateException $e) {
            $this->assertSame('legacy_template', $e->getErrorCode());
        }

        $this->expectException(PdfRenderException::class);
        PdfmeTemplate::prepare(['version' => '4.6.0', 'objects' => []], []);
    }
}
