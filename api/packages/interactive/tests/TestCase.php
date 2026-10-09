<?php

namespace Ulams\Interactive\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Courses\Tests\Models\User;
use Ulams\Interactive\Database\Seeders\InteractivePermissionSeeder;
use Ulams\Interactive\UlamsInteractiveServiceProvider;
use Ulams\Uploads\Tests\ZipFixtures;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;
    use ZipFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        $this->seed(InteractivePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->cleanZipFixtures();
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            UlamsInteractiveServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }

    /**
     * Zips a fixture folder (tests/Fixtures/packages/<name>) at test time; no binary zips are committed.
     *
     * @param array<string, mixed> $manifest top-level manifest keys to replace
     * @param array<string, string> $extra extra entries (name => contents)
     * @param string[] $without entries to leave out
     */
    protected function packageZip(string $fixture, array $manifest = [], array $extra = [], array $without = []): string
    {
        $root = __DIR__ . '/Fixtures/packages/' . $fixture;
        $entries = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $entries[str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1))] = (string) file_get_contents($file->getPathname());
        }
        if ($manifest !== []) {
            $current = json_decode($entries['ulams-interactive.json'], true);
            $entries['ulams-interactive.json'] = json_encode(array_merge($current, $manifest), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        foreach ($without as $name) {
            unset($entries[$name]);
        }

        return $this->makeZip($extra + $entries);
    }

    protected function upload(string $path, string $name = 'package.zip'): UploadedFile
    {
        return new UploadedFile($path, $name, null, null, true);
    }
}
