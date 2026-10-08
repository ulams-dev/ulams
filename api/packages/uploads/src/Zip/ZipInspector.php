<?php

namespace Ulams\Uploads\Zip;

use Ulams\Uploads\Exceptions\UploadRejected;
use ZipArchive;

/**
 * Checks an archive before anything is extracted: entry names (zip-slip, absolute paths,
 * backslashes, drive letters, NUL bytes, duplicates), symlink entries and zip-bomb limits
 * (entry count, total and per-entry uncompressed size, compression ratio).
 *
 * The sizes checked here come from the central directory, which an attacker controls;
 * {@see SafeExtractor} counts the real bytes again while it extracts.
 */
class ZipInspector
{
    /** Ratio checks only apply to entries larger than this; small files can compress very well. */
    private const RATIO_MIN_SIZE = 1024 * 1024;

    /**
     * @return ZipEntry[] entries in archive order, directories included
     * @throws UploadRejected
     */
    public function inspect(string $zipPath, ZipLimits $limits): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new UploadRejected('invalid_archive', 'The file is not a readable zip archive.');
        }

        try {
            return $this->inspectOpen($zip, $limits);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return ZipEntry[]
     * @throws UploadRejected
     */
    public function inspectOpen(ZipArchive $zip, ZipLimits $limits): array
    {
        if ($zip->numFiles > $limits->maxEntries) {
            throw new UploadRejected(
                'too_many_entries',
                sprintf('The archive has %d entries; the limit is %d.', $zip->numFiles, $limits->maxEntries)
            );
        }

        $entries = [];
        $seen = [];
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i, ZipArchive::FL_UNCHANGED);
            if ($stat === false) {
                throw new UploadRejected('invalid_archive', 'The archive has an unreadable entry.');
            }
            $name = (string) $stat['name'];

            if ($this->isSymlink($zip, $i)) {
                throw new UploadRejected('symlink', sprintf('The archive contains a symbolic link (%s).', $this->printable($name)));
            }

            $path = self::normalise($name);
            if ($path === '') {
                continue;
            }

            $isDirectory = str_ends_with($name, '/');
            $key = ($isDirectory ? 'd:' : 'f:') . $path;
            if (isset($seen[$key])) {
                throw new UploadRejected('duplicate_entry', sprintf('The archive contains "%s" twice.', $this->printable($path)));
            }
            $seen[$key] = true;

            $size = (int) $stat['size'];
            $compressed = (int) $stat['comp_size'];

            if (!$isDirectory) {
                if ($size > $limits->maxEntrySize) {
                    throw new UploadRejected('zip_bomb', sprintf('"%s" is larger than the per-file limit.', $this->printable($path)));
                }
                if ($size > self::RATIO_MIN_SIZE && ($compressed === 0 || $size / $compressed > $limits->maxRatio)) {
                    throw new UploadRejected('zip_bomb', sprintf('"%s" has a suspicious compression ratio.', $this->printable($path)));
                }
                $total += $size;
                if ($total > $limits->maxUncompressed) {
                    throw new UploadRejected('zip_bomb', 'The archive expands beyond the allowed size.');
                }
            }

            $entries[] = new ZipEntry($i, $name, $path, $isDirectory, $size, $compressed);
        }

        return $entries;
    }

    /**
     * Normalises an entry name to a relative path, or rejects it.
     *
     * @throws UploadRejected
     */
    public static function normalise(string $name): string
    {
        if ($name === '') {
            return '';
        }
        if (str_contains($name, "\0") || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            throw new UploadRejected('invalid_name', 'The archive contains a file name with control characters.');
        }
        if (str_contains($name, '\\')) {
            throw new UploadRejected('zip_slip', sprintf('The archive contains a path with a backslash (%s).', $name));
        }
        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
            throw new UploadRejected('absolute_path', sprintf('The archive contains an absolute path (%s).', $name));
        }

        $segments = [];
        foreach (explode('/', $name) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new UploadRejected('zip_slip', sprintf('The archive contains a path that leaves its folder (%s).', $name));
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function isSymlink(ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attr = 0;
        if (!$zip->getExternalAttributesIndex($index, $opsys, $attr, ZipArchive::FL_UNCHANGED)) {
            return false;
        }

        return $opsys === ZipArchive::OPSYS_UNIX && ((($attr >> 16) & 0xF000) === 0xA000);
    }

    private function printable(string $name): string
    {
        return mb_strimwidth((string) preg_replace('/[\x00-\x1F\x7F]/', '?', $name), 0, 120, '…');
    }
}
