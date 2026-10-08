<?php

namespace Ulams\Uploads\Zip;

final class ZipLimits
{
    public function __construct(
        public readonly int $maxEntries = 5000,
        public readonly int $maxUncompressed = 2 * 1024 * 1024 * 1024,
        public readonly int $maxEntrySize = 1024 * 1024 * 1024,
        public readonly int $maxRatio = 200,
        // Treat "/a/b" as "a/b" instead of rejecting it. Only for course exports made before the
        // exporter was fixed (it wrote every entry with a leading slash).
        public readonly bool $stripLeadingSlash = false,
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
            (bool) ($config['strip_leading_slash'] ?? false),
        );
    }
}
