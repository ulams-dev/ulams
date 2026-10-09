<?php

namespace Ulams\Tenancy\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Ulams\Tenancy\Services\H5PServiceConfigExporter;
use Ulams\Tenancy\Tests\TestCase;

class H5PServiceConfigExporterTest extends TestCase
{
    private string $root;
    private string $envDir;
    private string $storage;
    private string $export;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/ulams-h5p-export-' . uniqid();
        $this->envDir = $this->root . '/api';
        $this->storage = $this->envDir . '/storage';
        $this->export = $this->root . '/h5p-config';
        mkdir($this->storage . '/acme_localhost', 0777, true);

        file_put_contents($this->envDir . '/.env', implode("\n", [
            'APP_KEY=base64:platformsecret',
            'APP_URL=http://api.localhost',
            'DB_HOST=postgres',
            'DB_DATABASE=default',
            'DB_PASSWORD="pa ss$word"',
            'AWS_BUCKET=ulams',
            'MAIL_PASSWORD=mailsecret',
        ]) . "\n");
        file_put_contents($this->envDir . '/.env.acme.localhost', implode("\n", [
            'APP_KEY=base64:acmesecret',
            'APP_URL=http://acme.localhost',
            'TENANT_SLUG=acme',
            'FRONTEND_URL=http://acme.app.localhost',
            'DB_DATABASE=ulams_acme',
            'DB_USERNAME=ulams_acme',
            'DB_PASSWORD=acme-db',
            'AWS_BUCKET=ulams-acme',
            'H5P_INTERNAL_TOKEN=acme-internal',
            'STRIPE_SECRET=sk_live_x',
        ]) . "\n");
        file_put_contents($this->storage . '/oauth-public.key', 'PLATFORM PUBLIC');
        file_put_contents($this->storage . '/oauth-private.key', 'PLATFORM PRIVATE');
        file_put_contents($this->storage . '/acme_localhost/oauth-public.key', 'ACME PUBLIC');
        file_put_contents($this->storage . '/acme_localhost/oauth-private.key', 'ACME PRIVATE');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->root);
        parent::tearDown();
    }

    private function exporter(?string $dir): H5PServiceConfigExporter
    {
        return new H5PServiceConfigExporter(new Filesystem(), $this->envDir, $this->storage, $dir);
    }

    public function testExportsOnlyWhatTheH5PServiceReads(): void
    {
        $exporter = $this->exporter($this->export);
        $exporter->exportPlatform();
        $exporter->exportTenant('acme.localhost', $this->storage . '/acme_localhost');

        $platform = file_get_contents($this->export . '/.env');
        $tenant = file_get_contents($this->export . '/.env.acme.localhost');

        $this->assertStringContainsString('DB_DATABASE=default', $platform);
        $this->assertStringContainsString('DB_PASSWORD="pa ss\\$word"', $platform);
        $this->assertStringNotContainsString('APP_KEY', $platform);
        $this->assertStringNotContainsString('mailsecret', $platform);

        $this->assertEquals([
            'APP_URL' => 'http://acme.localhost',
            'FRONTEND_URL' => 'http://acme.app.localhost',
            'TENANT_SLUG' => 'acme',
            'DB_DATABASE' => 'ulams_acme',
            'DB_USERNAME' => 'ulams_acme',
            'DB_PASSWORD' => 'acme-db',
            'AWS_BUCKET' => 'ulams-acme',
            'H5P_INTERNAL_TOKEN' => 'acme-internal',
        ], H5PServiceConfigExporter::filter($tenant));
        $this->assertStringNotContainsString('acmesecret', $tenant);
        $this->assertStringNotContainsString('sk_live_x', $tenant);

        $this->assertSame('PLATFORM PUBLIC', file_get_contents($this->export . '/keys/oauth-public.key'));
        $this->assertSame('ACME PUBLIC', file_get_contents($this->export . '/keys/acme_localhost/oauth-public.key'));
        $this->assertFileDoesNotExist($this->export . '/keys/oauth-private.key');
        $this->assertFileDoesNotExist($this->export . '/keys/acme_localhost/oauth-private.key');
        $this->assertSame([], array_values(array_filter(
            scandir($this->export),
            fn (string $file) => str_contains($file, '.tmp-'),
        )));
    }

    public function testRemoveDeletesTheTenantFiles(): void
    {
        $exporter = $this->exporter($this->export);
        $exporter->exportTenant('acme.localhost', $this->storage . '/acme_localhost');
        $exporter->remove('acme.localhost');

        $this->assertFileDoesNotExist($this->export . '/.env.acme.localhost');
        $this->assertDirectoryDoesNotExist($this->export . '/keys/acme_localhost');
    }

    public function testDisabledWithoutADirectory(): void
    {
        $exporter = $this->exporter(null);
        $this->assertFalse($exporter->enabled());
        $exporter->exportPlatform();
        $exporter->exportTenant('acme.localhost', $this->storage . '/acme_localhost');
        $this->assertDirectoryDoesNotExist($this->export);
    }

    public function testRejectsHostsThatEscapeTheDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->exporter($this->export)->exportTenant('../../etc', $this->storage);
    }

    public function testCommandExportsThePlatformAndProvisionedTenants(): void
    {
        $this->app->instance(H5PServiceConfigExporter::class, $this->exporter($this->export));

        $this->artisan('ulams:h5p:export-config')->assertExitCode(0);

        $this->assertFileExists($this->export . '/.env');
        $this->assertFileExists($this->export . '/keys/oauth-public.key');
    }
}
