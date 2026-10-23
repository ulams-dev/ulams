<?php

namespace Ulams\CourseBuilder\StyleProfile;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\StyleProfile;

/**
 * The style check (ADR 0196): a deterministic critic that reports where a course departs from an approved style profile.
 * It never rewrites anything and never calls a model, so it cannot grade or decide access: it reports, the author decides.
 *
 * Rules and their severity:
 * - banned phrase: `blocking` when the profile says so (`banned.blocking`), else a warning. The only rule that can block.
 * - terminology: a term to avoid, or a product name in the wrong casing: warning.
 * - components: a component the profile disallows in the landing page: warning.
 * - images: Markdown image without alternative text, when the profile requires it: warning.
 * - reading level (per language, approximate formulas) and sentence length: hints, never blocking.
 *
 * Every finding carries the passages of the style guide it comes from (`citations`), so the author can read the rule.
 */
final class StyleCheck
{
    private const TEXT_KEYS = ['markdown', 'text', 'stem', 'question', 'answer', 'explanation', 'caption', 'summary', 'description', 'subtitle', 'title'];

    /** @var array<string,string> fragment id → label */
    private array $labels = [];

    /**
     * @param array<string,mixed> $doc a Course Blueprint
     * @return array{blocking:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>,hints:array<int,array<string,mixed>>}
     */
    public function run(array $doc, StyleProfile $profile, ?string $language = null, string $prefix = ''): array
    {
        $rules = (array) $profile->profile;
        $language = (string) ($doc['course']['language'] ?? $language ?? 'en');
        $out = ['blocking' => [], 'warnings' => [], 'hints' => []];

        foreach (self::units($doc) as $unit) {
            foreach ($this->banned($unit, $rules) as $finding) {
                $out[$finding['severity'] === 'blocking' ? 'blocking' : 'warnings'][] = $this->finish($finding, $prefix);
            }
            foreach ($this->terminology($unit, $rules) as $finding) {
                $out['warnings'][] = $this->finish($finding, $prefix);
            }
            foreach ($this->images($unit, $rules) as $finding) {
                $out['warnings'][] = $this->finish($finding, $prefix);
            }
        }
        foreach ($this->components($doc, $rules) as $finding) {
            $out['warnings'][] = $this->finish($finding, $prefix);
        }
        foreach (self::lessonTexts($doc) as $lesson) {
            foreach ($this->readability($lesson, $rules, $language) as $finding) {
                $out['hints'][] = $this->finish($finding, $prefix);
            }
        }

        return $out;
    }

    // ---- what is checked --------------------------------------------------------------------------------------------

    /**
     * Every piece of text of the course with the element it belongs to.
     *
     * @return array<int,array{elementId:string,title:string,text:string,markdown:string}>
     */
    public static function units(array $doc): array
    {
        $units = [];
        $course = (array) ($doc['course'] ?? []);
        if (is_string($course['id'] ?? null)) {
            $text = trim(implode("\n", array_filter(array_map(fn ($k) => is_string($course[$k] ?? null) ? $course[$k] : null, ['title', 'subtitle', 'description']))));
            if ($text !== '') {
                $units[] = ['elementId' => $course['id'], 'title' => (string) ($course['title'] ?? ''), 'text' => $text, 'markdown' => ''];
            }
        }
        foreach (Blueprint::lessons($doc) as $item) {
            $lesson = $item['lesson'];
            $title = (string) ($lesson['title'] ?? '');
            $units[] = ['elementId' => (string) $lesson['id'], 'title' => $title, 'text' => $title, 'markdown' => ''];
            foreach ((array) ($lesson['blocks'] ?? []) as $block) {
                $md = (string) ($block['markdown'] ?? '');
                $units[] = ['elementId' => (string) ($block['id'] ?? $lesson['id']), 'title' => $title, 'text' => self::strings($block), 'markdown' => $md];
            }
            foreach ((array) ($lesson['quiz']['questions'] ?? []) as $question) {
                $units[] = ['elementId' => (string) ($question['id'] ?? $lesson['id']), 'title' => $title, 'text' => self::strings($question), 'markdown' => ''];
            }
            foreach ((array) ($lesson['selfChecks'] ?? []) as $question) {
                $units[] = ['elementId' => (string) ($question['id'] ?? $lesson['id']), 'title' => $title, 'text' => self::strings($question), 'markdown' => ''];
            }
        }
        foreach ((array) ($doc['finalTest']['questions'] ?? []) as $question) {
            $units[] = ['elementId' => (string) ($question['id'] ?? ''), 'title' => (string) ($doc['finalTest']['title'] ?? ''), 'text' => self::strings($question), 'markdown' => ''];
        }

        return array_values(array_filter($units, fn ($u) => trim($u['text']) !== '' || $u['markdown'] !== ''));
    }

    /** The prose strings of an element (the keys that hold text, nested), one per line. */
    private static function strings(mixed $node): string
    {
        $out = [];
        $walk = static function (mixed $value, ?string $key) use (&$walk, &$out): void {
            if (is_string($value)) {
                if ($key !== null && in_array($key, self::TEXT_KEYS, true) && trim($value) !== '') {
                    $out[] = $value;
                }

                return;
            }
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    if (in_array($k, ['citations', 'objectiveIds', 'meta', 'asset'], true)) {
                        continue;
                    }
                    $walk($v, is_string($k) ? $k : $key);
                }
            }
        };
        $walk($node, null);

        return implode("\n", $out);
    }

    /** @return array<int,array{elementId:string,title:string,markdown:string}> one entry per lesson with all its prose */
    private static function lessonTexts(array $doc): array
    {
        $out = [];
        foreach (Blueprint::lessons($doc) as $item) {
            $lesson = $item['lesson'];
            $out[] = [
                'elementId' => (string) $lesson['id'],
                'title' => (string) ($lesson['title'] ?? ''),
                'markdown' => implode("\n\n", array_map(fn ($b) => (string) ($b['markdown'] ?? ''), (array) ($lesson['blocks'] ?? []))),
            ];
        }

        return $out;
    }

    // ---- the rules --------------------------------------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    private function banned(array $unit, array $rules): array
    {
        $out = [];
        $blocking = ($rules['banned']['blocking'] ?? false) === true;
        $haystack = $unit['text'] . "\n" . $unit['markdown'];
        foreach ((array) ($rules['banned']['phrases'] ?? []) as $item) {
            $match = self::find((string) $item['phrase'], $haystack, false);
            if ($match === null) {
                continue;
            }
            $out[] = [
                'rule' => 'banned', 'code' => $blocking ? 'style_banned' : 'style_banned_warning', 'severity' => $blocking ? 'blocking' : 'warning', 'unit' => $unit,
                'term' => $item['phrase'], 'quote' => $match,
                'message' => "“{$unit['title']}”: “{$item['phrase']}” is a banned phrase of the style guide",
                'citations' => (array) ($item['citations'] ?? []),
            ];
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private function terminology(array $unit, array $rules): array
    {
        $out = [];
        $haystack = $unit['text'] . "\n" . $unit['markdown'];
        foreach ((array) ($rules['terminology'] ?? []) as $item) {
            $use = (string) $item['use'];
            $casingFlagged = false;
            foreach ((array) ($item['avoid'] ?? []) as $avoid) {
                // a "wrong" spelling that differs from the right one only by case is a casing rule: match it exactly
                $caseOnly = mb_strtolower((string) $avoid) === mb_strtolower($use);
                $match = self::find((string) $avoid, $haystack, $caseOnly);
                if ($match !== null) {
                    $casingFlagged = $casingFlagged || $caseOnly;
                    $out[] = [
                        'rule' => 'terminology', 'code' => 'style_terminology', 'severity' => 'warning', 'unit' => $unit, 'term' => $avoid, 'use' => $use, 'quote' => $match,
                        'message' => "“{$unit['title']}”: use “{$use}”, not “{$avoid}”",
                        'citations' => (array) ($item['citations'] ?? []),
                    ];
                }
            }
            // a name with capitals (a product name) is written exactly as the guide spells it
            if ($use !== mb_strtolower($use) && !$casingFlagged) {
                $wrong = self::wrongCasing($use, $haystack);
                if ($wrong !== null) {
                    $out[] = [
                        'rule' => 'casing', 'code' => 'style_casing', 'severity' => 'warning', 'unit' => $unit, 'term' => $wrong, 'use' => $use, 'quote' => $wrong,
                        'message' => "“{$unit['title']}”: write “{$use}” exactly like that, not “{$wrong}”",
                        'citations' => (array) ($item['citations'] ?? []),
                    ];
                }
            }
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private function images(array $unit, array $rules): array
    {
        if (($rules['imagery']['altTextRequired'] ?? false) !== true || $unit['markdown'] === '') {
            return [];
        }
        if (preg_match('/!\[\s*\]\([^)]*\)/', (string) preg_replace('/```.*?```|~~~.*?~~~/s', '', $unit['markdown'])) !== 1) {
            return [];
        }

        return [[
            'rule' => 'images', 'code' => 'style_alt_text', 'severity' => 'warning', 'unit' => $unit, 'term' => null, 'quote' => null,
            'message' => "“{$unit['title']}”: an image has no alternative text, and the style guide requires it",
            'citations' => (array) ($rules['imagery']['citations'] ?? []),
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function components(array $doc, array $rules): array
    {
        $disallowed = (array) ($rules['components']['disallowed'] ?? []);
        $landing = $doc['pages']['landing'] ?? null;
        if ($disallowed === [] || !is_array($landing)) {
            return [];
        }
        $found = [];
        $walk = static function (mixed $node) use (&$walk, &$found, $disallowed): void {
            if (!is_array($node)) {
                return;
            }
            if (is_string($node['component'] ?? null) && in_array($node['component'], $disallowed, true)) {
                $found[$node['component']] = true;
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk($landing);
        $out = [];
        foreach (array_keys($found) as $name) {
            $out[] = [
                'rule' => 'components', 'code' => 'style_component', 'severity' => 'warning',
                'unit' => ['elementId' => (string) ($doc['course']['id'] ?? ''), 'title' => (string) ($doc['course']['title'] ?? ''), 'text' => '', 'markdown' => ''],
                'term' => $name, 'quote' => null,
                'message' => "The landing page uses the component {$name}, which the style guide does not allow",
                'citations' => (array) ($rules['components']['citations'] ?? []),
            ];
        }

        return $out;
    }

    /**
     * Reading level and sentence length: hints, because the formulas are crude.
     *
     * @return array<int,array<string,mixed>>
     */
    private function readability(array $lesson, array $rules, string $language): array
    {
        $out = [];
        $sentences = Readability::sentences($lesson['markdown']);
        $unit = ['elementId' => $lesson['elementId'], 'title' => $lesson['title'], 'text' => '', 'markdown' => ''];
        foreach ((array) ($rules['readingLevel'] ?? []) as $item) {
            if ($item['language'] !== $language) {
                continue;
            }
            $target = Readability::targetGrade((string) $item['level']);
            $result = $target === null ? null : Readability::grade($language, $sentences);
            if ($result !== null && $result['grade'] - $target > 1.0) {
                $out[] = [
                    'rule' => 'reading_level', 'code' => 'style_reading_level', 'severity' => 'hint', 'unit' => $unit, 'term' => $item['level'], 'quote' => null,
                    'message' => sprintf('“%s” reads at about grade %s (an approximate formula) and the style guide asks for %s', $lesson['title'], rtrim(rtrim(number_format($result['grade'], 1), '0'), '.'), $item['level']),
                    'citations' => (array) ($item['citations'] ?? []),
                ];
            }
        }
        $max = (int) ($rules['voice']['sentenceLength'] ?? 0);
        if ($max > 0 && count($sentences) >= 3) {
            $over = 0;
            foreach ($sentences as $sentence) {
                $count = $language === 'zh' ? (int) preg_match_all('/\p{Han}/u', $sentence) : count(Readability::words($sentence));
                $over += $count > $max ? 1 : 0;
            }
            if ($over / count($sentences) > 0.2) {
                $out[] = [
                    'rule' => 'sentence_length', 'code' => 'style_sentence_length', 'severity' => 'hint', 'unit' => $unit, 'term' => (string) $max, 'quote' => null,
                    'message' => sprintf('“%s”: %d of %d sentences are longer than %d words, the length the style guide asks for', $lesson['title'], $over, count($sentences), $max),
                    'citations' => (array) ($rules['voice']['citations'] ?? []),
                ];
            }
        }

        return $out;
    }

    // ---- matching and shaping ---------------------------------------------------------------------------------------

    /** The first match of a whole word or phrase (case-insensitive unless `$exact`), with a little context, or null. */
    private static function find(string $term, string $text, bool $exact): ?string
    {
        if (trim($term) === '') {
            return null;
        }
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($term, '/') . '(?![\p{L}\p{N}])/u' . ($exact ? '' : 'i');
        if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return self::context($text, (int) $m[0][1], strlen($m[0][0]));
    }

    private static function wrongCasing(string $use, string $text): ?string
    {
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($use, '/') . '(?![\p{L}\p{N}])/ui';
        if (preg_match_all($pattern, $text, $all) < 1) {
            return null;
        }
        foreach ($all[0] as $hit) {
            if ($hit !== $use) {
                return $hit;
            }
        }

        return null;
    }

    private static function context(string $text, int $byteOffset, int $byteLength): string
    {
        $start = mb_strlen(substr($text, 0, $byteOffset));
        $length = mb_strlen(substr($text, $byteOffset, $byteLength));
        $from = max(0, $start - 30);
        $snippet = trim(preg_replace('/\s+/u', ' ', mb_substr($text, $from, $length + 60)) ?? '');

        return ($from > 0 ? '…' : '') . $snippet . '…';
    }

    /** @param array<string,mixed> $finding @return array<string,mixed> */
    private function finish(array $finding, string $prefix): array
    {
        $ids = array_values(array_unique(array_map('strval', $finding['citations'])));
        $citations = array_map(fn ($id) => ['id' => $id, 'label' => $this->label($id)], $ids);
        $suffix = $citations === [] ? '' : ' (style guide ' . implode(', ', array_column($citations, 'label')) . ')';

        return array_filter([
            'code' => $finding['code'],
            'rule' => $finding['rule'],
            'severity' => $finding['severity'],
            'elementId' => $finding['unit']['elementId'],
            'message' => $prefix . $finding['message'] . $suffix,
            'term' => $finding['term'],
            'use' => $finding['use'] ?? null,
            'quote' => $finding['quote'],
            'citations' => $citations,
        ], fn ($v) => $v !== null);
    }

    private function label(string $id): string
    {
        if (!isset($this->labels[$id])) {
            $fragment = Fragment::query()->find($id);
            $this->labels[$id] = $fragment?->label() ?? $id;
        }

        return $this->labels[$id];
    }
}
