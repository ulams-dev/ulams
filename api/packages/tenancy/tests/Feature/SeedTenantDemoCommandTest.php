<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Ulams\Auth\Models\User;
use Ulams\Settings\Models\Setting;
use Ulams\Tenancy\Tests\TestCase;

class SeedTenantDemoCommandTest extends TestCase
{
    public function testCreatesDemoUsersAndPublicSettings(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'coffee']);
        putenv('INITIAL_USER_EMAIL=admin@coffee.ulams.app');
        putenv('INITIAL_USER_PASSWORD=demo-pass');

        try {
            $this->artisan('ulams:tenant:seed-demo', [
                '--users' => 2,
                '--name' => 'The Coffee Atlas',
                '--theme' => 'coffee',
                '--accent' => '#C2552D',
                '--front-url' => 'http://coffee.app.localhost',
                '--email-domain' => 'coffee.ulams.app',
            ])->assertExitCode(0);
            // idempotent
            $this->artisan('ulams:tenant:seed-demo', ['--users' => 2, '--email-domain' => 'coffee.ulams.app'])->assertExitCode(0);
        } finally {
            putenv('INITIAL_USER_EMAIL');
            putenv('INITIAL_USER_PASSWORD');
        }

        $expected = [
            'admin@coffee.ulams.app' => 'admin',
            'tutor@coffee.ulams.app' => 'tutor',
            'student1@coffee.ulams.app' => 'student',
            'student2@coffee.ulams.app' => 'student',
        ];
        foreach ($expected as $email => $role) {
            $users = User::query()->where('email', $email)->get();
            $this->assertCount(1, $users, $email);
            $this->assertTrue($users[0]->hasRole($role), "{$email} is {$role}");
            $this->assertTrue(Hash::check('demo-pass', $users[0]->password));
            $this->assertNotEmpty($users[0]->first_name);
        }

        $this->getJson('/api/settings')->assertOk()
            ->assertJsonPath('data.global.companyName', 'The Coffee Atlas')
            ->assertJsonPath('data.global.frontURL', 'http://coffee.app.localhost')
            ->assertJsonPath('data.theme.theme', 'coffee')
            ->assertJsonPath('data.theme.accent', '#C2552D');
        $this->assertSame(1, Setting::query()->where(['group' => 'theme', 'key' => 'theme'])->count());
    }

    public function testRefusesToSeedThePlatform(): void
    {
        $this->artisan('ulams:tenant:seed-demo')->assertExitCode(1);
    }
}
