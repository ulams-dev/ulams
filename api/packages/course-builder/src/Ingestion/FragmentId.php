<?php

namespace Ulams\CourseBuilder\Ingestion;

/**
 * Stable fragment ids: `frg_` + the first 12 characters of base32(SHA-256(source key + heading
 * path + ordinal within the heading)). The id depends on the position in the heading tree, not on
 * the text, so fixing a typo keeps it and moving a section changes it.
 */
final class FragmentId
{
    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyz234567';

    /** @param string[] $headingPath */
    public static function make(string $sourceKey, array $headingPath, int $ordinal): string
    {
        $digest = hash('sha256', $sourceKey . "\x1F" . implode("\x1E", $headingPath) . "\x1F" . $ordinal, true);

        return 'frg_' . substr(self::base32($digest), 0, 12);
    }

    public static function isValid(string $id): bool
    {
        return (bool) preg_match('/^frg_[a-z2-7]{12}$/', $id);
    }

    private static function base32(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }
}
