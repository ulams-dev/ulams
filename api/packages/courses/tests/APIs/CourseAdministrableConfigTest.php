<?php

namespace Ulams\Courses\Tests\APIs;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Enum\CoursesConstant;
use Ulams\Courses\Enum\CourseVisibilityEnum;
use Ulams\Courses\Enum\PlatformVisibility;
use Ulams\Courses\Tests\TestCase;
use Ulams\Settings\Database\Seeders\PermissionTableSeeder as SettingsPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;

class CourseAdministrableConfigTest extends TestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

    /**
     * @test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);
        $this->seed(SettingsPermissionSeeder::class);

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');

        Config::set('ulams_settings.use_database', true);
    }

    public function test_course_administrable_config()
    {
        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/config',
        );
        $this->response->assertOk();
        $this->response->assertJsonFragment([
            'ulams_courses' => [
                'platform_visibility' => PlatformVisibility::VISIBILITY_PUBLIC,
                'reminder_of_deadline_count_days' => CoursesConstant::REMINDER_OF_DEADLINE_COUNT_DAYS,
                'course_visibility' => CourseVisibilityEnum::SHOW_ALL,
            ]
        ]);

        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/admin/config',
        );
        $this->response->assertOk();
        $this->response->assertJsonFragment([
            'ulams_courses' => [
                'platform_visibility' => [
                    'full_key' => 'ulams_courses.platform_visibility',
                    'key' => 'platform_visibility',
                    'rules' => ['required', 'string', 'in:public,registered'],
                    'public' => true,
                    'readonly' => false,
                    'value' => 'public',
                ],
                'reminder_of_deadline_count_days' => [
                    'full_key' => 'ulams_courses.reminder_of_deadline_count_days',
                    'key' => 'reminder_of_deadline_count_days',
                    'rules' => ['integer', 'min: 1'],
                    'public' => true,
                    'readonly' => false,
                    'value' => CoursesConstant::REMINDER_OF_DEADLINE_COUNT_DAYS,
                ],
                'course_visibility' => [
                    'full_key' => 'ulams_courses.course_visibility',
                    'key' => 'course_visibility',
                    'rules' => ['required', 'string', 'in:' . implode(',', CourseVisibilityEnum::getValues())],
                    'public' => true,
                    'readonly' => false,
                    'value' => 'show_all',
                ],
            ]
        ]);

        $this->response = $this->actingAs($this->user, 'api')->json(
            'POST',
            '/api/admin/config',
            [
                'config' => [
                    [
                        'key' => 'ulams_courses.platform_visibility',
                        'value' => PlatformVisibility::VISIBILITY_REGISTERED
                    ],
                    [
                        'key' => 'ulams_courses.course_visibility',
                        'value' => CourseVisibilityEnum::SHOW_ONLY_MY,
                    ],
                ]
            ]
        );
        $this->response->assertOk();

        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/config',
        );
        $this->response->assertOk();
        $this->response->assertJsonFragment([
            'ulams_courses' => [
                'platform_visibility' => PlatformVisibility::VISIBILITY_REGISTERED,
                'reminder_of_deadline_count_days' => CoursesConstant::REMINDER_OF_DEADLINE_COUNT_DAYS,
                'course_visibility' => CourseVisibilityEnum::SHOW_ONLY_MY,
            ]
        ]);
    }
}
