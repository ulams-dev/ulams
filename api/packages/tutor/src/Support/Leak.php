<?php

namespace Ulams\Tutor\Support;

/**
 * Deterministic check that an answer does not contain a correct quiz option (plan 8.2 step 6). Texts of three
 * or more words leak when at least 80 % of their words are in the answer; shorter ones when the whole
 * phrase occurs as words. Short, common answers are caught by the phrase rule, so the check errs on the
 * side of refusing.
 */
final class Leak
{
    /** @param string[] $correct */
    public static function detects(string $answer, array $correct): bool
    {
        $haystack = ' ' . implode(' ', self::words($answer)) . ' ';
        $answerWords = array_flip(self::words($answer));
        foreach ($correct as $text) {
            $words = self::words($text);
            if ($words === []) {
                continue;
            }
            if (count($words) >= 3) {
                $hit = count(array_filter($words, fn ($w) => isset($answerWords[$w])));
                if ($hit / count($words) >= 0.8) {
                    return true;
                }
            } elseif (str_contains($haystack, ' ' . implode(' ', $words) . ' ')) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] */
    public static function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
