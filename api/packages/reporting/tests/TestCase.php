<?php

namespace Ulams\Reporting\Tests;

use Spatie\Permission\Models\Role;
use Ulams\Ai\Tests\TestCase as AiTestCase;
use Ulams\Core\Enums\UserRole;
use Ulams\Reporting\Database\Seeders\ReportingPermissionSeeder;
use Ulams\Reporting\UlamsReportingServiceProvider;

/** The AI test case (users, Passport, fake driver, `ai_calls`) with the reporting package loaded. */
class TestCase extends AiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([UserRole::ADMIN, UserRole::TUTOR, UserRole::STUDENT] as $role) {
            Role::findOrCreate($role, 'api');
        }
        $this->seed(ReportingPermissionSeeder::class);
    }

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), UlamsReportingServiceProvider::class];
    }
}
