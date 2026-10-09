<?php

namespace Ulams\Adapt\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Adapt\Database\Seeders\AdaptPermissionSeeder;
use Ulams\Adapt\UlamsAdaptServiceProvider;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Courses\Tests\Models\User;
use Ulams\Uploads\Tests\ZipFixtures;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;
    use ZipFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        $this->seed(AdaptPermissionSeeder::class);
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
            UlamsAdaptServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('scorm', [
            'table_names' => [
                'user_table' => 'users',
                'scorm_table' => 'scorm',
                'scorm_sco_table' => 'scorm_sco',
                'scorm_sco_tracking_table' => 'scorm_sco_tracking',
            ],
            'disk' => 'local',
        ]);
    }

    /** A minimal valid Adapt course source (one page, one article, one block, two components). */
    public static function source(): array
    {
        return [
            'course' => ['_id' => 'course', 'title' => 'Adapt fixture'],
            'config' => ['_defaultLanguage' => 'en', '_spoor' => ['_isEnabled' => true]],
            'contentObjects' => [
                ['_id' => 'co-05', '_parentId' => 'course', '_type' => 'page', 'title' => 'Welcome'],
            ],
            'articles' => [
                ['_id' => 'a-05', '_parentId' => 'co-05', '_type' => 'article'],
            ],
            'blocks' => [
                ['_id' => 'b-05', '_parentId' => 'a-05', '_type' => 'block'],
            ],
            'components' => [
                ['_id' => 'c-05', '_parentId' => 'b-05', '_component' => 'text', '_layout' => 'full', 'body' => 'Hello'],
                ['_id' => 'c-10', '_parentId' => 'b-05', '_component' => 'mcq', '_layout' => 'full', '_items' => []],
            ],
        ];
    }
}
