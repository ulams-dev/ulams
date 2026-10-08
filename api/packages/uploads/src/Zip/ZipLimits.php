<?php

namespace Ulams\Uploads\Zip;

final class ZipLimits
{
    public function __construct(
        public readonly int $maxEntries = 5000,
        public readonly int $maxUncompressed = 2 * 1024 * 1024 * 1024,
        public readonly int $maxEntrySize = 1024 * 1024 * 1024,
        public readonly int $maxRatio = 200,
    ) {
    }

    public static function fromConfig(string $profile = 'package'): self
    {
        $config = (array) config('ulams_uploads.zip.' . $profile, []);

        return new self(
            (int) ($config['max_entries'] ?? 5000),
            (int) ($config['max_uncompressed'] ?? 2 * 1024 * 1024 * 1024),
            (int) ($config['max_entry_size'] ?? 1024 * 1024 * 1024),
            (int) ($config['max_ratio'] ?? 200),
        );
    }
}
