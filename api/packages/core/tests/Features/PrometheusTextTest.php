<?php

namespace Ulams\Core\Tests\Features;

use Ulams\Core\Observability\Metric;
use Ulams\Core\Observability\PrometheusText;
use Ulams\Core\Tests\Concerns\ParsesPrometheusText;
use Ulams\Core\Tests\TestCase;

class PrometheusTextTest extends TestCase
{
    use ParsesPrometheusText;

    public function testRendersHelpTypeAndEscapedLabels(): void
    {
        $text = PrometheusText::render([
            Metric::gauge('ulams_demo', "Line one\nline two", [
                [3, ['queue' => 'default']],
                [0.25, ['host' => 'a"b\\c', 'note' => "x\ny"]],
                [1],
            ]),
            Metric::gauge('ulams_empty', 'Skipped.', []),
        ]);

        $this->assertStringContainsString("# HELP ulams_demo Line one\\nline two\n", $text);
        $this->assertStringContainsString("# TYPE ulams_demo gauge\n", $text);
        $this->assertStringContainsString('ulams_demo{queue="default"} 3' . "\n", $text);
        $this->assertStringContainsString('ulams_demo{host="a\\"b\\\\c",note="x\\ny"} 0.25' . "\n", $text);
        $this->assertStringContainsString("ulams_demo 1\n", $text);
        $this->assertStringNotContainsString('ulams_empty', $text);

        $families = $this->parsePrometheusText($text);
        $this->assertSame('a"b\\c', $families['ulams_demo']['samples'][1]['labels']['host']);
        $this->assertCount(3, $families['ulams_demo']['samples']);
    }

    public function testSpecialFloats(): void
    {
        $text = PrometheusText::render([Metric::gauge('ulams_f', 'F.', [[INF], [-INF], [NAN], [1.5e-7]])]);

        $this->assertStringContainsString("ulams_f +Inf\n", $text);
        $this->assertStringContainsString("ulams_f -Inf\n", $text);
        $this->assertStringContainsString("ulams_f NaN\n", $text);
        $this->assertStringContainsString("ulams_f 0\n", $text);
    }
}
