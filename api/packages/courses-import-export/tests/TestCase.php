<?php

namespace Ulams\CoursesImportExport\Tests;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Courses\AuthServiceProvider;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Courses\Tests\Models\User as UserTest;
use Ulams\CoursesImportExport\UlamsCoursesImportExportServiceProvider;
use Ulams\HeadlessH5P\HeadlessH5PServiceProvider;
use Ulams\Scorm\UlamsScormServiceProvider;
use Ulams\Tags\UlamsTagsServiceProvider;
use Ulams\TopicTypes\UlamsTopicTypesServiceProvider;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    protected $response;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
    }

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            UlamsCategoriesServiceProvider::class,
            UlamsCourseServiceProvider::class,
            AuthServiceProvider::class,
            UlamsScormServiceProvider::class,
            UlamsTagsServiceProvider::class,
            UlamsCoursesImportExportServiceProvider::class,
            UlamsSettingsServiceProvider::class,
            UlamsTopicTypesServiceProvider::class,
            HeadlessH5PServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', UserTest::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('database.connections.mysql.strict', false);
        $app['config']->set('app.debug', (bool) env('APP_DEBUG', true));
        $app['config']->set('ulams.tags.ignore_migrations', false);
        $app['config']->set('hh5p.h5p_export', true);
        $app['config']->set('filesystems.disks.local', [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ]);

        $app['config']->set('scorm', [
            'table_names' => [
                'user_table' => 'users',
                'scorm_table' => 'scorm',
                'scorm_sco_table' => 'scorm_sco',
                'scorm_sco_tracking_table' => 'scorm_sco_tracking',
            ],
            // Scorm directory. You may create a custom path in file system
            'disk' => 'local',
        ]);
    }

    public function assertApiResponse(array $actualData)
    {
        $this->assertApiSuccess();

        $response = json_decode($this->response->getContent(), true);
        $responseData = $response['data'];

        $this->assertNotEmpty($responseData['id']);
        $this->assertModelData($actualData, $responseData);
    }

    public function assertApiSuccess()
    {
        $this->response->assertJson(['success' => true]);
    }

    public function assertModelData(array $actualData, array $expectedData)
    {
        foreach ($actualData as $key => $value) {
            if (in_array($key, ['created_at', 'updated_at'])) {
                continue;
            }
            $this->assertEquals($actualData[$key], $expectedData[$key]);
        }
    }
}
