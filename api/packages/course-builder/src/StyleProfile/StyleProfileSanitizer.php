<?php

namespace Ulams\CourseBuilder\StyleProfile;

use Ulams\CourseBuilder\Publish\AccentContrast;
use Ulams\Settings\Support\ThemePresets;

/**
 * Turns whatever a model (or an author's form) returned into a valid style profile (ADR 0196): unknown keys are
 * dropped, values out of range are dropped, and every drop is reported as a warning the author sees. Nothing here
 * can be widened by the text of a style guide: the set of keys, the ranges and the catalogue of components are
 * fixed in this class, so an injected instruction can at most produce a value that is dropped.
 *
 * With `$requireCitations` (the model's proposal) a value that cites no fragment of the style guide is dropped too:
 * no uncited claims. Values the author writes by hand keep an empty citation list and are marked as the author's.
 */
final class StyleProfileSanitizer
{
    public const SCHEMA_VERSION = 1;
    public const PERSONS = ['you', 'we', 'neutral'];
    public const MAX_TERMS = 100;
    public const MAX_BANNED = 200;
    public const MAX_AVOID = 10;
    public const MAX_EXAMPLES = 3;
    public const MAX_EXAMPLE_CHARS = 600;
    public const MAX_COMPONENTS = 40;
    private const LEVEL = '/^(A1|A2|B1|B2|C1|C2|grade-([1-9]|1[0-6]))$/';

    /** @var string[] */
    private array $warnings = [];

    /**
     * @param array<string,bool> $fragments ids of the style guide's fragments (citations must be among them)
     * @param string[] $components names of the page catalogue manifest
     */
    public function __construct(private readonly array $fragments, private readonly array $components, private readonly bool $requireCitations)
    {
    }

    /** @return array<string,mixed> the profile with its keys in a fixed order, empty parts left out */
    public static function empty(): array
    {
        return ['schemaVersion' => self::SCHEMA_VERSION];
    }

    /**
     * @param array<string,mixed> $raw
     * @return array{profile:array<string,mixed>,warnings:string[]}
     */
    public function sanitize(array $raw): array
    {
        $this->warnings = [];
        $out = self::empty();
        foreach (array_diff(array_keys($raw), ['schemaVersion', 'theme', 'voice', 'readingLevel', 'terminology', 'banned', 'components', 'imagery', 'examples']) as $key) {
            $this->warn((string) $key, 'is not part of the style profile and was ignored');
        }
        foreach (['theme', 'voice', 'readingLevel', 'terminology', 'banned', 'components', 'imagery', 'examples'] as $part) {
            if (!array_key_exists($part, $raw) || $raw[$part] === null || $raw[$part] === [] || $raw[$part] === '') {
                continue;
            }
            $value = $this->{$part}($raw[$part]);
            if ($value !== null) {
                $out[$part] = $value;
            }
        }

        return ['profile' => $out, 'warnings' => $this->warnings];
    }

    private function warn(string $path, string $message): void
    {
        $this->warnings[] = "{$path} {$message}";
    }

    /** @return string[]|null citations that resolve; null when the value must be dropped for lack of one */
    private function citations(mixed $raw, string $path): ?array
    {
        $ids = [];
        foreach (is_array($raw) ? $raw : [] as $id) {
            if (is_string($id) && isset($this->fragments[$id])) {
                $ids[$id] = $id;
            } elseif (is_string($id) && $id !== '') {
                $this->warn($path, "cited {$id}, which is not a fragment of the style guide; that citation was dropped");
            }
        }
        if ($ids === [] && $this->requireCitations) {
            $this->warn($path, 'cites no passage of the style guide and was dropped');

            return null;
        }

        return array_values($ids);
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' || mb_strlen($value) > $max ? null : $value;
    }

    /** Plain text only: no markup, no links, no control characters (a style value is never rendered as HTML or followed). */
    private static function plain(mixed $value, int $max): ?string
    {
        $text = self::text($value, $max);
        if ($text === null || preg_match('/[<>]|https?:|www\.|\{\{|\p{Cc}/u', $text)) {
            return null;
        }

        return $text;
    }

    /** @return array<string,mixed>|null */
    private function theme(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return $this->bad('theme', 'must be an object');
        }
        $out = [];
        if (isset($raw['preset'])) {
            ThemePresets::has($raw['preset']) ? $out['preset'] = $raw['preset'] : $this->warn('theme.preset', 'is not a known theme preset and was dropped');
        }
        foreach (['accent', 'secondary'] as $key) {
            if (isset($raw[$key])) {
                is_string($raw[$key]) && preg_match(ThemePresets::ACCENT_PATTERN, $raw[$key]) ? $out[$key] = strtolower($raw[$key]) : $this->warn("theme.{$key}", 'must be a six digit hex colour and was dropped');
            }
        }
        if (isset($out['accent'], $out['preset'])) {
            $adjusted = AccentContrast::adjust($out['accent'], $out['preset']);
            if ($adjusted !== null && $adjusted['adjusted']) {
                $this->warn('theme.accent', "does not reach a 5:1 contrast on the {$out['preset']} theme; the site will show {$adjusted['value']}");
            }
        }
        $fonts = [];
        foreach (is_array($raw['fonts'] ?? null) ? $raw['fonts'] : [] as $font) {
            // family names only: system or self-hosted fonts (air-gapped principle), never a web-font URL
            $name = self::text($font, 60);
            if ($name !== null && preg_match('/^[\p{L}\p{N}][\p{L}\p{N} \-]*$/u', $name)) {
                $fonts[$name] = $name;
            } else {
                $this->warn('theme.fonts', 'accepts font family names only (web-font URLs are ignored)');
            }
        }
        if ($fonts !== []) {
            $out['fonts'] = array_slice(array_values($fonts), 0, 4);
        }
        if (isset($raw['logoFileId'])) {
            is_string($raw['logoFileId']) && preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $raw['logoFileId']) ? $out['logoFileId'] = $raw['logoFileId'] : $this->warn('theme.logoFileId', 'is not a file id and was dropped');
        }
        if ($out === []) {
            return null;
        }
        $citations = $this->citations($raw['citations'] ?? [], 'theme');

        return $citations === null ? null : $out + ['citations' => $citations];
    }

    /** @return array<string,mixed>|null */
    private function voice(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return $this->bad('voice', 'must be an object');
        }
        $out = [];
        if (isset($raw['person'])) {
            in_array($raw['person'], self::PERSONS, true) ? $out['person'] = $raw['person'] : $this->warn('voice.person', 'must be you, we or neutral and was dropped');
        }
        if (isset($raw['formality'])) {
            is_int($raw['formality']) && $raw['formality'] >= 1 && $raw['formality'] <= 5 ? $out['formality'] = $raw['formality'] : $this->warn('voice.formality', 'must be a whole number from 1 to 5 and was dropped');
        }
        if (isset($raw['sentenceLength'])) {
            is_int($raw['sentenceLength']) && $raw['sentenceLength'] >= 5 && $raw['sentenceLength'] <= 60 ? $out['sentenceLength'] = $raw['sentenceLength'] : $this->warn('voice.sentenceLength', 'must be between 5 and 60 words and was dropped');
        }
        if ($out === []) {
            return null;
        }
        $citations = $this->citations($raw['citations'] ?? [], 'voice');

        return $citations === null ? null : $out + ['citations' => $citations];
    }

    /** @return array<int,array<string,mixed>>|null */
    private function readingLevel(mixed $raw): ?array
    {
        $items = [];
        foreach ($this->list($raw, 'readingLevel') as $i => $item) {
            $language = is_array($item) && is_string($item['language'] ?? null) ? strtolower($item['language']) : '';
            $level = is_array($item) && is_string($item['level'] ?? null) ? $item['level'] : '';
            if (!preg_match('/^[a-z]{2}$/', $language) || !preg_match(self::LEVEL, $level)) {
                $this->warn("readingLevel[{$i}]", 'needs a two letter language and a CEFR level (A1 to C2) or grade-1 to grade-16, and was dropped');
                continue;
            }
            $citations = $this->citations($item['citations'] ?? [], "readingLevel[{$i}]");
            if ($citations !== null) {
                $items[$language] = ['language' => $language, 'level' => $level, 'citations' => $citations];
            }
        }

        return $items === [] ? null : array_values($items);
    }

    /** @return array<int,array<string,mixed>>|null */
    private function terminology(mixed $raw): ?array
    {
        $items = [];
        foreach ($this->list($raw, 'terminology') as $i => $item) {
            $use = is_array($item) ? self::plain($item['use'] ?? null, 80) : null;
            if ($use === null) {
                $this->warn("terminology[{$i}]", 'needs a plain `use` term of at most 80 characters and was dropped');
                continue;
            }
            $avoid = [];
            foreach (is_array($item['avoid'] ?? null) ? $item['avoid'] : [] as $term) {
                $term = self::plain($term, 80);
                if ($term !== null && $term !== $use) {
                    $avoid[$term] = $term;
                }
            }
            $citations = $this->citations($item['citations'] ?? [], "terminology[{$i}]");
            if ($citations !== null && count($items) < self::MAX_TERMS) {
                $items[$use] = ['use' => $use, 'avoid' => array_slice(array_values($avoid), 0, self::MAX_AVOID), 'citations' => $citations];
            }
        }

        return $items === [] ? null : array_values($items);
    }

    /** @return array<string,mixed>|null */
    private function banned(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return $this->bad('banned', 'must be an object');
        }
        $phrases = [];
        foreach ($this->list($raw['phrases'] ?? [], 'banned.phrases') as $i => $item) {
            $phrase = is_array($item) ? self::plain($item['phrase'] ?? null, 120) : null;
            if ($phrase === null) {
                $this->warn("banned.phrases[{$i}]", 'needs a plain phrase of at most 120 characters and was dropped');
                continue;
            }
            $citations = $this->citations($item['citations'] ?? [], "banned.phrases[{$i}]");
            if ($citations !== null && count($phrases) < self::MAX_BANNED) {
                $phrases[mb_strtolower($phrase)] = ['phrase' => $phrase, 'citations' => $citations];
            }
        }
        if ($phrases === []) {
            return null;
        }

        // `blocking` is the author's decision, never the model's: the extractor resets it to false
        return ['blocking' => ($raw['blocking'] ?? false) === true, 'phrases' => array_values($phrases)];
    }

    /** @return array<string,mixed>|null */
    private function components(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return $this->bad('components', 'must be an object');
        }
        $out = [];
        foreach (['preferred', 'disallowed'] as $key) {
            $names = [];
            foreach (is_array($raw[$key] ?? null) ? $raw[$key] : [] as $name) {
                if (is_string($name) && in_array($name, $this->components, true)) {
                    $names[$name] = $name;
                } else {
                    $this->warn("components.{$key}", 'named ' . (is_string($name) ? $name : 'a value') . ', which is not in the catalogue, and it was dropped');
                }
            }
            if ($key === 'preferred') {
                $both = array_intersect_key($names, array_flip(array_map('strval', (array) ($raw['disallowed'] ?? []))));
                foreach ($both as $name) {
                    unset($names[$name]);
                    $this->warn('components', "lists {$name} as both preferred and disallowed; it stays disallowed");
                }
            }
            if ($names !== []) {
                $out[$key] = array_slice(array_values($names), 0, self::MAX_COMPONENTS);
            }
        }
        if ($out === []) {
            return null;
        }
        $citations = $this->citations($raw['citations'] ?? [], 'components');

        return $citations === null ? null : $out + ['citations' => $citations];
    }

    /** @return array<string,mixed>|null */
    private function imagery(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return $this->bad('imagery', 'must be an object');
        }
        $out = [];
        foreach (['altTextRequired', 'decorativeAllowed', 'noTextInImages'] as $key) {
            if (isset($raw[$key])) {
                is_bool($raw[$key]) ? $out[$key] = $raw[$key] : $this->warn("imagery.{$key}", 'must be true or false and was dropped');
            }
        }
        if ($out === []) {
            return null;
        }
        $citations = $this->citations($raw['citations'] ?? [], 'imagery');

        return $citations === null ? null : $out + ['citations' => $citations];
    }

    /** @return array<int,array<string,mixed>>|null */
    private function examples(mixed $raw): ?array
    {
        $items = [];
        foreach ($this->list($raw, 'examples') as $i => $item) {
            $text = is_array($item) ? self::plain($item['text'] ?? null, self::MAX_EXAMPLE_CHARS) : null;
            if ($text === null) {
                $this->warn("examples[{$i}]", 'needs plain text of at most ' . self::MAX_EXAMPLE_CHARS . ' characters and was dropped');
                continue;
            }
            $citations = $this->citations($item['citations'] ?? [], "examples[{$i}]");
            if ($citations !== null) {
                if (count($items) >= self::MAX_EXAMPLES) {
                    $this->warn('examples', 'keeps the first ' . self::MAX_EXAMPLES . ' examples only');
                    break;
                }
                $items[] = ['text' => $text, 'citations' => $citations];
            }
        }

        return $items === [] ? null : $items;
    }

    /** @return array<int,mixed> */
    private function list(mixed $raw, string $path): array
    {
        if (!is_array($raw) || (!array_is_list($raw) && $raw !== [])) {
            $this->warn($path, 'must be a list and was dropped');

            return [];
        }

        return $raw;
    }

    private function bad(string $path, string $message): null
    {
        $this->warn($path, "{$message} and was dropped");

        return null;
    }
}
