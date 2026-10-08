<?php

namespace Ulams\Tenancy\Console;

use Faker\Factory as FakerFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Ulams\Core\Enums\UserRole;
use Ulams\Settings\Models\Setting;
use Ulams\Tenancy\Support\TenantContext;

/**
 * Creates the demo users and the public settings the front reads. Runs inside a tenant
 * (`--domain=<host>`); `ulams:tenant:create` calls it as its last step.
 */
class SeedTenantDemoCommand extends Command
{
    protected $signature = 'ulams:tenant:seed-demo
        {--users=5 : Number of students}
        {--name= : Display name, stored as global.companyName}
        {--theme= : Front theme preset key, stored as theme.theme}
        {--accent= : Accent colour, stored as theme.accent}
        {--front-url= : Stored as global.frontURL}
        {--email-domain= : Domain of the demo e-mail addresses}';

    protected $description = 'Create demo users and public settings for the current tenant (internal step of ulams:tenant:create)';

    protected $hidden = true;

    public function handle(): int
    {
        if (TenantContext::isPlatform()) {
            $this->error('This command seeds a tenant: run it with --domain=<tenant host>.');

            return self::FAILURE;
        }

        $domain = (string) ($this->option('email-domain') ?: TenantContext::slug() . '.ulams.app');
        $password = (string) env('INITIAL_USER_PASSWORD', config('ulams_tenancy.demo_password'));
        $faker = FakerFactory::create();

        $this->seedUser((string) env('INITIAL_USER_EMAIL', 'admin@' . $domain), UserRole::ADMIN, $password, $faker);
        $this->seedUser('tutor@' . $domain, UserRole::TUTOR, $password, $faker);
        for ($i = 1; $i <= (int) $this->option('users'); $i++) {
            $this->seedUser("student{$i}@{$domain}", UserRole::STUDENT, $password, $faker);
        }

        if ($this->option('name')) {
            $this->setting('global', 'companyName', $this->option('name'));
        } elseif (!Setting::query()->where(['group' => 'global', 'key' => 'companyName'])->exists()) {
            $this->setting('global', 'companyName', (string) config('app.name'));
        }
        if ($this->option('front-url')) {
            $this->setting('global', 'frontURL', $this->option('front-url'));
        }
        if ($this->option('theme')) {
            $this->setting('theme', 'theme', $this->option('theme'));
        }
        if ($this->option('accent')) {
            $this->setting('theme', 'accent', $this->option('accent'));
        }

        $this->info('Demo users and settings ready.');

        return self::SUCCESS;
    }

    private function seedUser(string $email, string $role, string $password, \Faker\Generator $faker): void
    {
        $model = config('auth.providers.users.model');
        $user = $model::query()->firstWhere('email', $email);

        if (!$user) {
            $user = new $model();
            $user->forceFill([
                'email' => $email,
                'first_name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'password' => Hash::make($password),
                'is_active' => true,
                'email_verified_at' => Carbon::now(),
            ])->save();
        }

        Role::findOrCreate($role, 'api');
        $user->guard_name = 'api';
        if (!$user->hasRole($role)) {
            $user->assignRole($role);
        }
        $this->line("  {$role}: {$email}");
    }

    private function setting(string $group, string $key, string $value): void
    {
        Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value, 'type' => 'text', 'public' => true, 'enumerable' => true, 'sort' => 0]
        );
    }
}
