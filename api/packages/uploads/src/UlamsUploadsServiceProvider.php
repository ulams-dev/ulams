<?php

namespace Ulams\Uploads;

use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use Ulams\Uploads\Scanning\ClamdScanner;
use Ulams\Uploads\Scanning\NullScanner;
use Ulams\Uploads\Scanning\VirusScannerContract;
use Ulams\Uploads\Storage\ActiveContentSafeS3Adapter;
use Ulams\Uploads\Zip\SafeExtractor;
use Ulams\Uploads\Zip\ZipInspector;

class UlamsUploadsServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'ulams_uploads';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        $this->app->singleton(VirusScannerContract::class, function () {
            if (config(self::CONFIG_KEY . '.scanner') !== 'clamd') {
                return new NullScanner();
            }
            $clamd = (array) config(self::CONFIG_KEY . '.clamd', []);

            return new ClamdScanner(
                (string) ($clamd['host'] ?? 'clamav'),
                (int) ($clamd['port'] ?? 3310),
                (int) ($clamd['timeout'] ?? 60),
                (bool) ($clamd['fail_closed'] ?? true),
            );
        });
        $this->app->singleton(ZipInspector::class);
        $this->app->singleton(SafeExtractor::class);
        $this->app->singleton(UploadGuard::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        $attachment = (array) config(self::CONFIG_KEY . '.attachment_extensions', []);
        $prefixes = (array) config(self::CONFIG_KEY . '.package_prefixes', []);

        // Same as FilesystemManager::createS3Driver, with the hardened adapter. The callback is
        // bound to the manager, so its protected helpers are available.
        Storage::extend('s3', function ($app, array $config) use ($attachment, $prefixes) {
            /** @var \Illuminate\Filesystem\FilesystemManager $this */
            $s3Config = $this->formatS3Config($config);
            $client = new S3Client($s3Config);
            $adapter = (new ActiveContentSafeS3Adapter(
                $client,
                $s3Config['bucket'],
                (string) ($s3Config['root'] ?? ''),
                new PortableVisibilityConverter($config['visibility'] ?? Visibility::PUBLIC),
                null,
                $config['options'] ?? [],
                $s3Config['stream_reads'] ?? false,
            ))->configureHardening($attachment, $prefixes);

            return new AwsS3V3Adapter($this->createFlysystem($adapter, $config), $adapter, $s3Config, $client);
        });
    }
}
