<?php

namespace Ulams\Tenancy\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Ulams\Tenancy\Support\PassportKeyPermissions;
use Ulams\Tenancy\Tests\TestCase;

class PassportKeyPermissionsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/ulams-keys-' . uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function testKeyFilesGetModesPassportAccepts(): void
    {
        foreach (['oauth-private.key', 'oauth-public.key'] as $file) {
            file_put_contents($this->directory . '/' . $file, 'key');
            chmod($this->directory . '/' . $file, 0775);
        }

        PassportKeyPermissions::apply($this->directory);
        clearstatcache();

        $this->assertSame('600', decoct(fileperms($this->directory . '/oauth-private.key') & 0777));
        $this->assertSame('640', decoct(fileperms($this->directory . '/oauth-public.key') & 0777));
    }

    public function testMissingKeyFilesAreSkipped(): void
    {
        PassportKeyPermissions::apply($this->directory);

        $this->assertFileDoesNotExist($this->directory . '/oauth-private.key');
    }
}
