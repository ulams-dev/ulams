<?php

namespace Ulams\CourseBuilder\StyleProfile;

/**
 * Crude readability formulas for the style check (ADR 0196). Every result is a hint about how a text reads, never a
 * verdict, and the formulas are approximate:
 *
 * - English: the Flesch-Kincaid grade level, with a vowel-group syllable count.
 * - Polish: the Pisarek index (the Polish adaptation of the Gunning fog index): the mean of the average sentence length in
 *   words and the percentage of words with four or more syllables. Read as a school grade.
 * - Chinese: a character-count heuristic: a sentence of 20 Han characters is about grade 6, and every 5 characters more or
 *   less is one grade more or less.
 *
 * Other languages have no formula here and are not checked.
 */
final class Readability
{
    /** CEFR level as the school grade it is compared on. */
    private const CEFR = ['A1' => 2.0, 'A2' => 4.0, 'B1' => 7.0, 'B2' => 10.0, 'C1' => 13.0, 'C2' => 16.0];

    public static function supports(string $language): bool
    {
        return in_array($language, ['en', 'pl', 'zh'], true);
    }

    /** The target level (`grade-8`, `B1`) as a grade, or null when it is not one. */
    public static function targetGrade(string $level): ?float
    {
        if (preg_match('/^grade-(\d{1,2})$/', $level, $m)) {
            return (float) $m[1];
        }

        return self::CEFR[$level] ?? null;
    }

    /** Plain sentences of a Markdown text: syntax, code and links removed. @return string[] */
    public static function sentences(string $markdown): array
    {
        $text = (string) preg_replace('/```.*?```|~~~.*?~~~/s', ' ', $markdown);
        $text = (string) preg_replace('/`[^`\n]*`/', ' ', $text);
        $text = (string) preg_replace('/!\[[^\]]*\]\([^)]*\)/', ' ', $text);
        $text = (string) preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text);
        $text = (string) preg_replace('/^\s*(#{1,6}|[-*+]|\d+[.)]|>)\s+/m', '', $text);
        $text = (string) preg_replace('/[|*_~]+/', ' ', $text);
        $parts = preg_split('/(?<=[.!?…。！？])\s+|(?<=[。！？])|\n{2,}/u', trim($text)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($s) => preg_match('/[\p{L}\p{N}]/u', $s) === 1));
    }

    /** @return string[] */
    public static function words(string $sentence): array
    {
        preg_match_all("/[\p{L}\p{N}]+(?:['’\-][\p{L}\p{N}]+)*/u", $sentence, $m);

        return $m[0];
    }

    /**
     * @param string[] $sentences
     * @return array{grade:float,sentences:int,words:int}|null null when the text is too short to say anything (under 3 sentences or 30 words) or the language has no formula
     */
    public static function grade(string $language, array $sentences): ?array
    {
        if (!self::supports($language) || count($sentences) < 3) {
            return null;
        }
        if ($language === 'zh') {
            $chars = array_map(fn ($s) => preg_match_all('/\p{Han}/u', $s), $sentences);
            $total = array_sum($chars);
            if ($total < 30) {
                return null;
            }
            $avg = $total / count($sentences);

            return ['grade' => round(max(1.0, 6 + ($avg - 20) / 5), 1), 'sentences' => count($sentences), 'words' => $total];
        }
        $words = 0;
        $syllables = 0;
        $long = 0;
        foreach ($sentences as $sentence) {
            foreach (self::words($sentence) as $word) {
                $count = $language === 'pl' ? self::syllablesPl($word) : self::syllablesEn($word);
                $words++;
                $syllables += $count;
                $long += $count >= 4 ? 1 : 0;
            }
        }
        if ($words < 30) {
            return null;
        }
        $perSentence = $words / count($sentences);
        $grade = $language === 'pl'
            ? ($perSentence + 100 * $long / $words) / 2
            : 0.39 * $perSentence + 11.8 * $syllables / $words - 15.59;

        return ['grade' => round(max(1.0, $grade), 1), 'sentences' => count($sentences), 'words' => $words];
    }

    public static function syllablesEn(string $word): int
    {
        $word = mb_strtolower(preg_replace('/[^\p{L}]/u', '', $word) ?? '');
        if ($word === '') {
            return 1;
        }
        if (mb_strlen($word) <= 3) {
            return 1;
        }
        $word = (string) preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/u', '', $word);
        $word = (string) preg_replace('/^y/u', '', $word);

        return max(1, preg_match_all('/[aeiouy]{1,2}/u', $word));
    }

    public static function syllablesPl(string $word): int
    {
        $word = mb_strtolower($word);
        // "ia", "ie", "io", "iu" after a consonant are one syllable in Polish
        $word = (string) preg_replace('/i([aeiouyąęó])/u', 'j$1', $word);

        return max(1, preg_match_all('/[aeiouyąęó]/u', $word));
    }
}
