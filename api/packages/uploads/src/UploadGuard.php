<?php

namespace Ulams\Uploads;

use finfo;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Ulams\Uploads\Exceptions\UploadRejected;
use Ulams\Uploads\Scanning\VirusScannerContract;
use Ulams\Uploads\Zip\ZipInspector;
use Ulams\Uploads\Zip\ZipLimits;

/**
 * Checks an upload against the policy of its kind (`config('ulams_uploads.policies')`):
 * size, extension, MIME type sniffed from the content, virus scan and, for archives, the
 * zip inspector. Used by the {@see Rules\SafeUpload} validation rule and by services that
 * receive files from elsewhere (imports).
 */
class UploadGuard
{
    public function __construct(
        private readonly VirusScannerContract $scanner,
        private readonly ZipInspector $inspector = new ZipInspector(),
    ) {
    }

    /**
     * @throws UploadRejected
     */
    public function check(UploadedFile|string $file, string $kind, ?string $originalName = null): void
    {
        $policy = config('ulams_uploads.policies.' . $kind);
        if (!is_array($policy)) {
            throw new InvalidArgumentException("Unknown upload kind [{$kind}].");
        }

        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        if ($path === false || !is_file($path)) {
            throw new UploadRejected('missing', 'The upload could not be read.');
        }
        $originalName ??= $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($path);

        $size = (int) filesize($path);
        $max = (int) ($policy['max_size'] ?? 0);
        if ($max > 0 && $size > $max) {
            throw new UploadRejected(
                'too_large',
                sprintf('The file is %s; the limit for %s uploads is %s.', $this->human($size), $kind, $this->human($max))
            );
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $extensions = array_map('strtolower', (array) ($policy['extensions'] ?? []));
        if ($extensions !== [] && !in_array($extension, $extensions, true)) {
            throw new UploadRejected(
                'wrong_type',
                sprintf('%s uploads must be %s files.', ucfirst($kind), implode(', ', array_map(fn ($e) => ".{$e}", $extensions)))
            );
        }

        $mimes = (array) ($policy['mimes'] ?? []);
        if ($mimes !== []) {
            $sniffed = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
            if (!in_array($sniffed, $mimes, true)) {
                throw new UploadRejected(
                    'wrong_type',
                    sprintf('The file content is %s, which is not allowed for %s uploads.', $sniffed ?: 'unknown', $kind)
                );
            }
        }

        $this->scanner->scan($path);

        if (!empty($policy['zip']) && $extension === 'zip') {
            $this->inspector->inspect($path, ZipLimits::fromConfig((string) $policy['zip']));
        }
    }

    public function limitsFor(string $kind): ZipLimits
    {
        return ZipLimits::fromConfig((string) (config('ulams_uploads.policies.' . $kind . '.zip') ?: 'package'));
    }

    private function human(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? round($bytes / 1024 / 1024, 1) . ' MB'
            : round($bytes / 1024, 1) . ' KB';
    }
}
