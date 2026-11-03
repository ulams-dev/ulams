<?php

namespace Ulams\Core\Storage;

use Closure;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RuntimeException;

/**
 * Gives a library that needs a file path (a PDF parser, `getimagesize`, `ZipArchive`, an image optimiser) a
 * path to an object that may live on S3.
 *
 * On a local disk the callback gets the file's own path and nothing is copied. On any other disk the object
 * is streamed to a temporary file, the callback runs, and the file is deleted afterwards, also when the
 * callback throws. `Storage::path()` on an S3 disk is only the object key, not a place on the machine, and
 * the public URL does not resolve inside a container, so neither is a way to open a file.
 */
final class LocalCopy
{
    /**
     * @template T
     *
     * @param Filesystem|string|null $disk a disk, a disk name, or null for the default disk
     * @param Closure(string): T $callback receives the local path; must not keep it after returning
     * @param string|null $extension extension of the temporary file, for libraries that sniff it (defaults to the object's)
     *
     * @return T
     *
     * @throws FileNotFoundException when the object does not exist
     */
    public static function with(Filesystem|string|null $disk, string $path, Closure $callback, ?string $extension = null): mixed
    {
        $disk = is_string($disk) || $disk === null ? Storage::disk($disk) : $disk;

        if (self::isLocal($disk)) {
            if (!$disk->exists($path)) {
                throw new FileNotFoundException($path);
            }

            return $callback($disk->path($path));
        }

        $stream = $disk->readStream($path);
        if (!is_resource($stream)) {
            throw new FileNotFoundException($path);
        }

        $extension ??= pathinfo($path, PATHINFO_EXTENSION);
        $temporary = tempnam(sys_get_temp_dir(), 'ulams-copy-');
        if ($temporary === false) {
            fclose($stream);
            throw new RuntimeException('Could not create a temporary file.');
        }
        $target = $extension !== '' ? $temporary . '.' . preg_replace('/[^A-Za-z0-9]/', '', $extension) : $temporary;

        try {
            $handle = fopen($target, 'wb');
            if ($handle === false || stream_copy_to_stream($stream, $handle) === false) {
                throw new RuntimeException("Could not copy {$path} to a temporary file.");
            }
            fclose($handle);

            return $callback($target);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($target);
            if ($target !== $temporary) {
                @unlink($temporary);
            }
        }
    }

    public static function isLocal(Filesystem $disk): bool
    {
        return $disk instanceof FilesystemAdapter && $disk->getAdapter() instanceof LocalFilesystemAdapter;
    }
}
