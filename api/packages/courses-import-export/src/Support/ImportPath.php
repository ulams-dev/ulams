<?php

namespace Ulams\CoursesImportExport\Support;

/**
 * Resolves a path read from an import's content.json against the extraction directory.
 * content.json is untrusted: a value like "../../../.env" must never make the import read
 * (and publish) a file outside the extracted archive.
 */
final class ImportPath
{
    /**
     * @return string|null the real path inside `$root`, or null when it does not exist there
     */
    public static function resolve(string $root, mixed $relative, bool $allowDirectory = false): ?string
    {
        if (!is_string($relative) || $relative === '' || str_contains($relative, "\0")) {
            return null;
        }

        $base = realpath($root);
        $real = realpath($root . DIRECTORY_SEPARATOR . $relative);
        if ($base === false || $real === false) {
            return null;
        }
        if (!str_starts_with($real, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            return null;
        }
        if (!$allowDirectory && !is_file($real)) {
            return null;
        }

        return $real;
    }
}
