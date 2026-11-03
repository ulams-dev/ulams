<?php

namespace Ulams\Core\Observability;

/**
 * Renders metric families in the Prometheus text exposition format (version 0.0.4).
 */
final class PrometheusText
{
    public const CONTENT_TYPE = 'text/plain; version=0.0.4; charset=utf-8';

    /** @param iterable<Metric> $metrics */
    public static function render(iterable $metrics): string
    {
        $out = '';
        foreach ($metrics as $metric) {
            if ($metric->samples === []) {
                continue;
            }
            $out .= '# HELP ' . $metric->name . ' ' . self::escapeHelp($metric->help) . "\n";
            $out .= '# TYPE ' . $metric->name . ' ' . $metric->type . "\n";
            foreach ($metric->samples as [$value, $labels]) {
                $out .= $metric->name . self::labels($labels) . ' ' . self::number($value) . "\n";
            }
        }

        return $out;
    }

    /** @param array<string, string|int> $labels */
    private static function labels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }
        $pairs = [];
        foreach ($labels as $name => $value) {
            $pairs[] = $name . '="' . self::escapeLabel((string) $value) . '"';
        }

        return '{' . implode(',', $pairs) . '}';
    }

    private static function number(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_nan($value)) {
            return 'NaN';
        }
        if (is_infinite($value)) {
            return $value > 0 ? '+Inf' : '-Inf';
        }

        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.') ?: '0';
    }

    private static function escapeLabel(string $value): string
    {
        return str_replace(["\\", "\n", '"'], ['\\\\', '\\n', '\\"'], $value);
    }

    private static function escapeHelp(string $value): string
    {
        return str_replace(["\\", "\n"], ['\\\\', '\\n'], $value);
    }
}
