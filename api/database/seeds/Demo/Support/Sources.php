<?php

namespace Database\Seeders\Demo\Support;

use RuntimeException;

/**
 * The cited sources of a demo course (Demo/content/<key>/sources.json, a copy of the package's own
 * source list; the demo-content unit tests keep the copies equal).
 *
 * Two entry shapes are read:
 *   {"title", "publisher", "url", "note"}            (gravity: a reference per id)
 *   {"st", "p", "s": [[title, url], ...]}             (poland: the publisher pages behind a figure)
 *
 * Course text cites a source as `{{src:ID}}` or `{{src:ID1,ID2}}`. {@see cite()} numbers the citations of
 * one text in order of first use and appends the list.
 */
class Sources
{
    /** @var array<string, array<string, mixed>> */
    private array $entries;

    public function __construct(string $file)
    {
        $json = json_decode((string) file_get_contents($file), true);
        if (!is_array($json)) {
            throw new RuntimeException("$file is not a JSON object");
        }
        $this->entries = $json;
    }

    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }

    /** @return array<int, string> */
    public function ids(): array
    {
        return array_keys($this->entries);
    }

    /** @return array<int, array{0: string, 1: string}> [title, url] pairs of one source */
    public function references(string $id): array
    {
        $entry = $this->entries[$id] ?? throw new RuntimeException("Unknown source id $id");
        if (isset($entry['s'])) {
            return array_map(fn (array $pair) => [(string) $pair[0], (string) $pair[1]], $entry['s']);
        }
        $title = (string) ($entry['title'] ?? $id);
        $publisher = (string) ($entry['publisher'] ?? '');

        return [[$publisher !== '' && !str_contains($title, $publisher) ? $title . ' (' . $publisher . ')' : $title, (string) ($entry['url'] ?? '')]];
    }

    /** "Reference period: 2025" for the poland entries, null otherwise. */
    public function period(string $id): ?string
    {
        $p = $this->entries[$id]['p'] ?? null;

        return $p === null ? null : (string) $p;
    }

    /** @return array<int, string> source ids cited by `{{src:...}}` in a text, in order of first use */
    public function cited(string $text): array
    {
        preg_match_all('/\{\{src:([^}]+)\}\}/', $text, $all);
        $ids = [];
        foreach ($all[1] as $group) {
            foreach (array_map('trim', explode(',', $group)) as $id) {
                $ids[$id] = true;
            }
        }
        foreach (array_keys($ids) as $id) {
            if (!$this->has($id)) {
                throw new RuntimeException("Unknown source id $id");
            }
        }

        return array_keys($ids);
    }

    /**
     * Replaces `{{src:...}}` with `[n]` and appends the numbered list. Ids that point at the same publisher
     * pages (poland has several ids per page) share one number.
     *
     * @param bool $compact one italic line (for the text beside an interactive) instead of a heading and list
     */
    public function cite(string $text, bool $compact = false, string $heading = 'Sources'): string
    {
        $ids = $this->cited($text);
        if ($ids === []) {
            return $text;
        }
        $number = [];
        $entries = [];
        foreach ($ids as $id) {
            $signature = json_encode($this->references($id));
            if (!isset($entries[$signature])) {
                $entries[$signature] = ['id' => $id, 'number' => count($entries) + 1];
            }
            $number[$id] = $entries[$signature]['number'];
        }
        $text = (string) preg_replace_callback('/\{\{src:([^}]+)\}\}/', function (array $m) use ($number) {
            $numbers = array_unique(array_map(fn (string $id) => $number[trim($id)], explode(',', $m[1])));
            sort($numbers);

            return '[' . implode(', ', $numbers) . ']';
        }, $text);

        if ($compact) {
            $items = array_map(fn (array $e) => sprintf('[%d] %s', $e['number'], $this->references($e['id'])[0][0]), array_values($entries));

            return rtrim($text) . "\n\n*" . $heading . ': ' . implode('; ', $items) . '*';
        }
        $lines = [];
        foreach ($entries as $e) {
            $parts = array_map(fn (array $pair) => $pair[1] !== '' ? sprintf('%s, <%s>', $pair[0], $pair[1]) : $pair[0], $this->references($e['id']));
            $period = $this->period($e['id']);
            $lines[] = sprintf('%d. %s%s', $e['number'], implode('; ', $parts), $period !== null ? ' (' . $period . ')' : '');
        }

        return rtrim($text) . "\n\n## " . $heading . "\n\n" . implode("\n", $lines) . "\n";
    }

    /** Short names ("publisher: title") of sources, for the Sources callout of a layout. */
    public function names(array $ids): string
    {
        $names = [];
        foreach ($ids as $id) {
            $names[] = $this->references($id)[0][0];
        }

        return implode('; ', array_unique($names));
    }
}
