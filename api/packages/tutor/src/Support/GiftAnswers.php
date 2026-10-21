<?php

namespace Ulams\Tutor\Support;

/** Reads the correct answers out of a GIFT question, for the leak check. Never shown to anyone. */
final class GiftAnswers
{
    /** @return string[] the texts of the correct options (both sides of a matching pair) */
    public static function correct(string $gift): array
    {
        if (!preg_match('/\{(.*)\}/s', $gift, $m)) {
            return [];
        }
        $out = [];
        foreach (preg_split('/(?=(?<!\\\\)[=~])/', $m[1], -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $token = trim($token);
            $mark = $token[0] ?? '';
            $text = trim(substr($token, 1));
            $weight = null;
            if (preg_match('/^%(-?\d+(?:\.\d+)?)%/', $text, $w)) {
                $weight = (float) $w[1];
                $text = trim(substr($text, strlen($w[0])));
            }
            $text = trim((string) preg_replace('/#.*$/s', '', $text));
            if ($text === '' || !(($mark === '=' && ($weight === null || $weight > 0)) || ($mark === '~' && $weight !== null && $weight >= 100))) {
                continue;
            }
            foreach (preg_split('/\s*->\s*/', $text) ?: [] as $part) {
                $part = trim(str_replace(['\\=', '\\~', '\\#', '\\{', '\\}'], ['=', '~', '#', '{', '}'], $part));
                if ($part !== '') {
                    $out[] = $part;
                }
            }
        }

        return array_values(array_unique($out));
    }
}
