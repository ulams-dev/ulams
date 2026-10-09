<?php

namespace Ulams\Uploads\Zip;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Ulams\Uploads\Exceptions\UploadRejected;
use ZipArchive;

/**
 * Extracts an archive entry by entry under a normalised path. Never calls
 * `ZipArchive::extractTo`. Every entry is inspected first ({@see ZipInspector}); while
 * copying, the real number of bytes is counted, so a central directory that lies about
 * sizes cannot get past the limits. On any failure the files written so far are removed.
 */
class SafeExtractor
{
    private const CHUNK = 1024 * 1024;

    public function __construct(private readonly ZipInspector $inspector = new ZipInspector())
    {
    }

    /**
     * @return string[] written paths, relative to the disk root
     * @throws UploadRejected
     */
    public function extractToDisk(string $zipPath, Filesystem $disk, string $prefix, ?ZipLimits $limits = null): array
    {
        $limits ??= ZipLimits::fromConfig();
        $prefix = trim(str_replace('\\', '/', $prefix), '/');

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new UploadRejected('invalid_archive', 'The file is not a readable zip archive.');
        }

        $written = [];
        try {
            $entries = $this->inspector->inspectOpen($zip, $limits);
            $total = 0;

            foreach ($entries as $entry) {
                $target = $prefix === '' ? $entry->path : $prefix . '/' . $entry->path;
                if ($entry->isDirectory) {
                    continue;
                }

                $source = $zip->getStreamIndex($entry->index);
                if ($source === false) {
                    throw new UploadRejected('invalid_archive', sprintf('"%s" cannot be read from the archive.', $entry->path));
                }

                $buffer = fopen('php://temp/maxmemory:' . (4 * 1024 * 1024), 'w+b');
                try {
                    $bytes = 0;
                    while (!feof($source)) {
                        $chunk = fread($source, self::CHUNK);
                        if ($chunk === false) {
                            throw new UploadRejected('invalid_archive', sprintf('"%s" cannot be read from the archive.', $entry->path));
                        }
                        $bytes += strlen($chunk);
                        $total += strlen($chunk);
                        if ($bytes > $entry->size || $bytes > $limits->maxEntrySize || $total > $limits->maxUncompressed) {
                            throw new UploadRejected('zip_bomb', 'The archive expands beyond its declared or allowed size.');
                        }
                        fwrite($buffer, $chunk);
                    }
                    rewind($buffer);
                    if ($disk->writeStream($target, $buffer) === false) {
                        throw new UploadRejected('storage_error', sprintf('"%s" could not be stored.', $entry->path));
                    }
                    $written[] = $target;
                } finally {
                    fclose($source);
                    if (is_resource($buffer)) {
                        fclose($buffer);
                    }
                }
            }
        } catch (Throwable $e) {
            $this->cleanup($disk, $written);
            throw $e;
        } finally {
            $zip->close();
        }

        return $written;
    }

    /**
     * Extracts into a local directory (created if missing).
     *
     * @return string[] written paths, relative to `$directory`
     * @throws UploadRejected
     */
    public function extractToDirectory(string $zipPath, string $directory, ?ZipLimits $limits = null): array
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new UploadRejected('storage_error', 'The extraction directory could not be created.');
        }

        return $this->extractToDisk($zipPath, Storage::build(['driver' => 'local', 'root' => $directory]), '', $limits);
    }

    /**
     * @param string[] $paths
     */
    private function cleanup(Filesystem $disk, array $paths): void
    {
        foreach ($paths as $path) {
            try {
                $disk->delete($path);
            } catch (Throwable) {
                // best effort
            }
        }
    }
}
