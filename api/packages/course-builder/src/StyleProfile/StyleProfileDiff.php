<?php

namespace Ulams\CourseBuilder\StyleProfile;

/**
 * What a proposed profile changes against the current one, value by value, for the author's review (ADR 0196).
 * Values are compared without their citations; a list item is identified by its key (a term, a phrase, a language),
 * so reordering is no change. Every change carries the citations of the proposed value.
 */
final class StyleProfileDiff
{
    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @return array<int,array{path:string,change:string,before:mixed,after:mixed,citations:string[]}>
     */
    public static function between(array $before, array $after): array
    {
        $old = self::flatten($before);
        $new = self::flatten($after);
        $changes = [];
        foreach ($new as $path => $entry) {
            if (!array_key_exists($path, $old)) {
                $changes[] = ['path' => $path, 'change' => 'added', 'before' => null, 'after' => $entry['value'], 'citations' => $entry['citations']];
            } elseif ($old[$path]['value'] !== $entry['value']) {
                $changes[] = ['path' => $path, 'change' => 'changed', 'before' => $old[$path]['value'], 'after' => $entry['value'], 'citations' => $entry['citations']];
            }
        }
        foreach ($old as $path => $entry) {
            if (!array_key_exists($path, $new)) {
                $changes[] = ['path' => $path, 'change' => 'removed', 'before' => $entry['value'], 'after' => null, 'citations' => []];
            }
        }

        return $changes;
    }

    /** @return array<string,array{value:mixed,citations:string[]}> */
    private static function flatten(array $profile): array
    {
        $out = [];
        $put = static function (string $path, mixed $value, mixed $citations) use (&$out): void {
            $out[$path] = ['value' => $value, 'citations' => array_values((array) $citations)];
        };
        foreach (['theme', 'voice', 'components', 'imagery'] as $part) {
            foreach ((array) ($profile[$part] ?? []) as $key => $value) {
                if ($key !== 'citations') {
                    $put("{$part}.{$key}", $value, $profile[$part]['citations'] ?? []);
                }
            }
        }
        foreach ((array) ($profile['readingLevel'] ?? []) as $item) {
            $put("readingLevel.{$item['language']}", $item['level'], $item['citations'] ?? []);
        }
        foreach ((array) ($profile['terminology'] ?? []) as $item) {
            $put("terminology.{$item['use']}", $item['avoid'] ?? [], $item['citations'] ?? []);
        }
        if (isset($profile['banned'])) {
            $put('banned.blocking', (bool) ($profile['banned']['blocking'] ?? false), []);
            foreach ((array) ($profile['banned']['phrases'] ?? []) as $item) {
                $put('banned.phrase.' . mb_strtolower($item['phrase']), $item['phrase'], $item['citations'] ?? []);
            }
        }
        foreach ((array) ($profile['examples'] ?? []) as $i => $item) {
            $put('examples.' . ($i + 1), $item['text'], $item['citations'] ?? []);
        }

        return $out;
    }
}
