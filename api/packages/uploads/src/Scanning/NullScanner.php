<?php

namespace Ulams\Uploads\Scanning;

/**
 * Default scanner: accepts every file. Enable clamd with UPLOADS_SCANNER=clamd.
 */
class NullScanner implements VirusScannerContract
{
    public function scan(string $path): void
    {
    }
}
