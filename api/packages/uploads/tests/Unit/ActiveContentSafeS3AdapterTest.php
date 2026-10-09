<?php

namespace Ulams\Uploads\Tests\Unit;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use Ulams\Uploads\Storage\ActiveContentSafeS3Adapter;
use Ulams\Uploads\Tests\TestCase;

class ActiveContentSafeS3AdapterTest extends TestCase
{
    public function testSvgAndHtmlOutsidePackagesAreServedAsAttachments(): void
    {
        $adapter = $this->adapter();

        $svg = $adapter->harden('categories/icon.svg', new Config());
        $this->assertSame('attachment', $svg->get('ContentDisposition'));
        $this->assertSame('image/svg+xml', $svg->get('ContentType'));

        $this->assertSame('attachment', $adapter->harden('files/page.html', new Config())->get('ContentDisposition'));
        $this->assertSame('attachment', $adapter->harden('files/noext', new Config())->get('ContentDisposition'));
    }

    public function testContentTypeComesFromTheExtension(): void
    {
        $png = $this->adapter()->harden('avatars/me.png', new Config());

        $this->assertSame('image/png', $png->get('ContentType'));
        $this->assertNull($png->get('ContentDisposition'));
    }

    public function testPackagePathsAreUntouched(): void
    {
        $config = $this->adapter()->harden('scorm/scorm_12/uuid/index.html', new Config());

        $this->assertNull($config->get('ContentDisposition'));
        $this->assertNull($config->get('ContentType'));
    }

    public function testTheS3DiskSendsTheHeadersOnPut(): void
    {
        $commands = [];
        $mock = new MockHandler();
        $mock->append(function (CommandInterface $command) use (&$commands) {
            $commands[] = $command;

            return new Result([]);
        });

        config(['filesystems.disks.hardened' => [
            'driver' => 's3',
            'key' => 'k',
            'secret' => 's',
            'region' => 'us-east-1',
            'bucket' => 'b',
            'endpoint' => 'http://localhost:9',
            'use_path_style_endpoint' => true,
            'handler' => $mock,
        ]]);

        $disk = Storage::disk('hardened');
        $this->assertInstanceOf(ActiveContentSafeS3Adapter::class, $disk->getAdapter());

        $disk->put('categories/icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->assertSame('PutObject', $commands[0]->getName());
        $this->assertSame('attachment', $commands[0]['ContentDisposition']);
        $this->assertSame('image/svg+xml', $commands[0]['ContentType']);
    }

    private function adapter(): ActiveContentSafeS3Adapter
    {
        return (new ActiveContentSafeS3Adapter(
            new S3Client(['region' => 'us-east-1', 'version' => 'latest', 'credentials' => ['key' => 'k', 'secret' => 's']]),
            'bucket'
        ))->configureHardening(config('ulams_uploads.attachment_extensions'), config('ulams_uploads.package_prefixes'));
    }
}
