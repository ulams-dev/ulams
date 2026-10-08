<?php

namespace Ulams\Uploads\Zip;

/**
 * One archive entry that passed the inspector. `path` is the normalised relative path
 * (forward slashes, no `.` segments, no trailing slash) under which it may be written.
 */
final class ZipEntry
{
    public function __construct(
        public readonly int $index,
        public readonly string $name,
        public readonly string $path,
        public readonly bool $isDirectory,
        public readonly int $size,
        public readonly int $compressedSize,
    ) {
    }
}
