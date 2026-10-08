<?php

namespace Ulams\Uploads\Storage;

use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Config;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;

/**
 * S3 adapter that stops uploaded files from running as active content on the bucket origin.
 *
 * - `Content-Type` comes from the file extension, not from the content, so an HTML or SVG
 *   payload renamed to `.png` is not served as `text/html`.
 * - SVG, HTML, XML and files of unknown type get `Content-Disposition: attachment`: a browser
 *   that opens the URL downloads them instead of rendering them (and running their scripts).
 *   `<img src>` ignores the header, so SVG images keep working in pages.
 *
 * Paths under the package prefixes (SCORM, cmi5, ...) are left alone: their HTML must render,
 * and they are served from the per-tenant content origin with its own CSP.
 */
class ActiveContentSafeS3Adapter extends AwsS3V3Adapter
{
    private const ACTIVE_TYPES = [
        'text/html',
        'application/xhtml+xml',
        'image/svg+xml',
        'text/xml',
        'application/xml',
        'text/xsl',
        'application/xslt+xml',
    ];

    /** @var string[] */
    private array $attachmentExtensions = [];

    /** @var string[] */
    private array $packagePrefixes = [];

    private ?ExtensionMimeTypeDetector $extensionDetector = null;

    /**
     * @param string[] $attachmentExtensions
     * @param string[] $packagePrefixes
     */
    public function configureHardening(array $attachmentExtensions, array $packagePrefixes): static
    {
        $this->attachmentExtensions = array_map('strtolower', $attachmentExtensions);
        $this->packagePrefixes = $packagePrefixes;

        return $this;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        parent::write($path, $contents, $this->harden($path, $config));
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        parent::writeStream($path, $contents, $this->harden($path, $config));
    }

    public function harden(string $path, Config $config): Config
    {
        $relative = ltrim($path, '/');
        foreach ($this->packagePrefixes as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return $config;
            }
        }

        $settings = [];
        $type = $config->get('ContentType');
        if ($type === null) {
            $this->extensionDetector ??= new ExtensionMimeTypeDetector();
            $type = $this->extensionDetector->detectMimeTypeFromPath($relative);
            if ($type !== null) {
                $settings['ContentType'] = $type;
            }
        }

        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        $active = $type === null
            || in_array($extension, $this->attachmentExtensions, true)
            || in_array(strtolower(explode(';', $type)[0]), self::ACTIVE_TYPES, true);

        if ($active && $config->get('ContentDisposition') === null) {
            $settings['ContentDisposition'] = 'attachment';
        }

        return $settings === [] ? $config : $config->extend($settings);
    }
}
