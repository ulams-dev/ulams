<?php

namespace Ulams\Core\Tests\Concerns;

/**
 * A strict reader of the Prometheus text exposition format, so a test fails when `GET /metrics`
 * would not scrape: every sample needs a preceding TYPE line, names and label sets must be valid.
 */
trait ParsesPrometheusText
{
    /**
     * @return array<string, array{type: string, help: string, samples: list<array{labels: array<string, string>, value: float}>}>
     */
    protected function parsePrometheusText(string $text): array
    {
        $families = [];
        $this->assertStringEndsWith("\n", $text, 'The exposition must end with a newline.');
        foreach (explode("\n", rtrim($text, "\n")) as $number => $line) {
            $where = 'line ' . ($number + 1) . ': ' . $line;
            if (preg_match('/^# HELP ([a-zA-Z_:][a-zA-Z0-9_:]*) (.*)$/', $line, $m)) {
                $families[$m[1]]['help'] = $m[2];
                $families[$m[1]]['samples'] ??= [];
                continue;
            }
            if (preg_match('/^# TYPE ([a-zA-Z_:][a-zA-Z0-9_:]*) (counter|gauge|histogram|summary|untyped)$/', $line, $m)) {
                $families[$m[1]]['type'] = $m[2];
                $families[$m[1]]['samples'] ??= [];
                continue;
            }
            $this->assertMatchesRegularExpression('/^([a-zA-Z_:][a-zA-Z0-9_:]*)(\{(.*)\})? (-?[0-9.]+(e[+-]?[0-9]+)?|NaN|\+Inf|-Inf)$/', $line, $where);
            preg_match('/^([a-zA-Z_:][a-zA-Z0-9_:]*)(?:\{(.*)\})? (\S+)$/', $line, $m);
            $labels = [];
            if (($m[2] ?? '') !== '') {
                $this->assertMatchesRegularExpression('/^([a-zA-Z_][a-zA-Z0-9_]*="(?:[^"\\\\]|\\\\.)*")(,[a-zA-Z_][a-zA-Z0-9_]*="(?:[^"\\\\]|\\\\.)*")*$/', $m[2], $where);
                preg_match_all('/([a-zA-Z_][a-zA-Z0-9_]*)="((?:[^"\\\\]|\\\\.)*)"/', $m[2], $pairs, PREG_SET_ORDER);
                foreach ($pairs as $pair) {
                    $labels[$pair[1]] = stripcslashes($pair[2]);
                }
            }
            $this->assertArrayHasKey($m[1], $families, "Sample without a TYPE line, {$where}");
            $families[$m[1]]['samples'][] = ['labels' => $labels, 'value' => (float) $m[3]];
        }

        return $families;
    }
}
