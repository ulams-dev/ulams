<?php

namespace Ulams\CodeExercises\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\CodeExercises\UlamsCodeExercisesServiceProvider;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Tests\Models\User;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        $this->seed(CoursesPermissionSeeder::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            UlamsCodeExercisesServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }

    /** @return array<int, array{path: string, content: string}> */
    protected function starterFiles(): array
    {
        return [['path' => 'sum.js', 'content' => "export function sum(a, b) {\n  return 0;\n}\n"]];
    }

    /** @return array<int, array{path: string, content: string}> */
    protected function visibleTests(): array
    {
        return [['path' => 'sum.test.js', 'content' => "import { sum } from './sum.js';\ntest('adds', () => expect(sum(1, 2)).toBe(3));\n"]];
    }

    /** @return array<string, mixed> the content fields of a valid topic */
    protected function exercise(array $override = []): array
    {
        return $override + [
            'language' => 'javascript',
            'files' => $this->starterFiles(),
            'visible_tests' => $this->visibleTests(),
            'text' => 'Make `sum` return the sum of its arguments.',
        ];
    }
}
