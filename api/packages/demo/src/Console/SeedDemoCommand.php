<?php

namespace Ulams\Demo\Console;

use Illuminate\Console\Command;
use Ulams\Demo\Enums\DemoRole;
use Ulams\Demo\Exceptions\DemoUserNotFoundException;
use Ulams\Demo\Services\Contracts\DemoServiceContract;
use Ulams\Demo\UlamsDemoServiceProvider;

/**
 * Seeds the tenant's demo course (DemoCoursesSeeder, experience = tenant slug) and gives the
 * demo student access to every published course, so a visitor who is logged in automatically
 * can open any course. The last step of `ulams:demo:reset`; safe to run again.
 */
class SeedDemoCommand extends Command
{
    protected $signature = 'ulams:demo:seed
        {--experience= : Demo experience to seed (coffee, oncall, nightsky, gravity, poland, ulam or all); defaults to ULAMS_DEMO_EXPERIENCE, then the tenant slug}
        {--skip-content : Only grant the demo student access to the published courses}';

    protected $description = 'Seed the demo courses of this tenant and give the demo student access to every published course';

    public function handle(DemoServiceContract $demo): int
    {
        if (!$this->option('skip-content')) {
            $this->seedContent();
        }

        try {
            $student = $demo->userFor(DemoRole::STUDENT);
        } catch (DemoUserNotFoundException $exception) {
            $this->warn($exception->getMessage());

            return self::SUCCESS;
        }

        $granted = $demo->grantCourseAccess($student);
        $this->info("Demo student {$student->email}: access to {$granted} more course(s).");

        return self::SUCCESS;
    }

    private function seedContent(): void
    {
        $config = UlamsDemoServiceProvider::CONFIG_KEY;
        $seeder = (string) config($config . '.content_seeder');
        if ($seeder === '' || !class_exists($seeder)) {
            $this->line('No demo content seeder (' . ($seeder ?: 'none') . '): demo courses skipped.');

            return;
        }

        $experience = strtolower((string) ($this->option('experience') ?: config($config . '.experience')));
        $known = defined($seeder . '::EXPERIENCES') ? array_keys(constant($seeder . '::EXPERIENCES')) : null;
        if ($experience === '' || ($known !== null && $experience !== 'all' && !in_array($experience, $known, true))) {
            $this->line("No demo experience for '{$experience}': demo courses skipped.");

            return;
        }

        $previous = getenv('DEMO_EXPERIENCE');
        $this->setEnv('DEMO_EXPERIENCE', $experience);
        try {
            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
        } finally {
            $this->setEnv('DEMO_EXPERIENCE', $previous === false ? null : $previous);
        }
    }

    private function setEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }
        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}
