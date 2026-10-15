<?php

namespace Ulams\CourseBuilder\Ingestion;

/**
 * PDF and DOCX extractors sometimes emit byte sequences that are not UTF-8. Postgres and the
 * event log both refuse those, so every stored string is cleaned first. Valid text is unchanged.
 */
final class TextEncoding
{
    public static function utf8(mixed $value): mixed
    {
        if (is_string($value)) {
            if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
                return $value;
            }
            $clean = iconv('UTF-8', 'UTF-8//IGNORE', $value);

            return $clean === false ? '' : $clean;
        }
        if (!is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::utf8($item);
        }

        return $value;
    }
}
