<?php

namespace EscolaLms\Files\Tests;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\Core\EscolaLmsServiceProvider;
use EscolaLms\Core\Models\User;
use EscolaLms\Files\Database\Seeders\DatabaseSeeder;
use EscolaLms\Files\Database\Seeders\PermissionTableSeeder;
use EscolaLms\Files\Enums\FilePermissionsEnum;
use EscolaLms\Files\EscolaLmsFilesServiceProvider;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends \EscolaLms\Core\Tests\TestCase
{
    protected Filesystem $disk;

    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionTableSeeder::class);
        $this->disk = Storage::fake();

        $user = User::factory()->create();
        $user->givePermissionTo(
            FilePermissionsEnum::FILE_LIST,
            FilePermissionsEnum::FILE_DELETE,
            FilePermissionsEnum::FILE_UPDATE,
            FilePermissionsEnum::FILE_CREATE,
        );
        Auth::setUser($user);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            EscolaLmsFilesServiceProvider::class,
            EscolaLmsServiceProvider::class,
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            EscolaLmsAuthServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('passport.client_uuids', false);
    }

    public function ignorePackageDiscoveriesFrom()
    {
        return [];
    }

    protected function shouldSeed()
    {
        return true;
    }

    protected function seeder()
    {
        return DatabaseSeeder::class;
    }

    protected function getWithQuery(string $url, array $parameters, array $headers = [], $authorize = false): TestResponse
    {
        if (empty($parameters)) {
            $query = $url;
        } else {
            $query = $url.'?'.http_build_query($parameters);
        }
        if ($authorize) {
            return $this->actingAs(auth()->user(), 'api')->get($query, $headers);
        }
        return $this->get($query, $headers);
    }

    protected function deleteWithQuery(string $url, array $parameters, array $headers = [], $authorize = false): TestResponse
    {
        if (empty($parameters)) {
            $query = $url;
        } else {
            $query = $url.'?'.http_build_query($parameters);
        }
        if ($authorize) {
            return $this->actingAs(auth()->user(), 'api')->delete($query, $headers);
        }
        return $this->delete($query, $headers);
    }
}
