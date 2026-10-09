<?php

namespace Ulams\Uploads\Scanning;

use Ulams\Uploads\Exceptions\UploadRejected;

interface VirusScannerContract
{
    /**
     * Scans a local file. Returns normally when it is clean.
     *
     * @throws UploadRejected with reason `infected` (or `scan_failed` when the scanner fails closed)
     */
    public function scan(string $path): void;
}
