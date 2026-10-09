<?php

namespace Database\Seeders;

use Database\Seeders\Demo\CoffeeAtlasExperience;
use Database\Seeders\Demo\DemoExperience;
use Database\Seeders\Demo\GravityExperience;
use Database\Seeders\Demo\NightSkyExperience;
use Database\Seeders\Demo\OnCallExperience;
use Database\Seeders\Demo\PolandExperience;
use Database\Seeders\Demo\UlamExperience;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Ulams\Auth\Dtos\UserSaveDto;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\Contracts\UserServiceContract;
use Ulams\Lrs\Database\Seeders\LrsSeeder;
use Ulams\Lrs\Models\Access;

/**
 * Seeds the demo courses described in front/docs/design/experiences.md
 * (The Coffee Atlas, On-Call, Night Sky Explorers) with all their media,
 * quizzes, projects, products, events and certificates, and the three free
 * interactive academies (Gravity Lab, Poland, Measured, The Scottish Book).
 *
 *   php artisan db:seed --class=DemoCoursesSeeder                      # all six
 *   DEMO_EXPERIENCE=coffee php artisan db:seed --class=DemoCoursesSeeder
 *   php artisan db:seed --class=DemoCoursesSeeder --domain=coffee.localhost
 *
 * The experience comes from DEMO_EXPERIENCE, then ULAMS_DEMO_EXPERIENCE (set
 * it in a tenant's .env to seed only that tenant's course), default "all";
 * several can be comma-separated. Re-running keeps existing demo courses
 * (matched by title) and refreshes their metadata, products and events;
 * DEMO_REFRESH=1 rebuilds them, DEMO_REFRESH_ASSETS=1 regenerates media.
 */
class DemoCoursesSeeder extends Seeder
{
    public const EXPERIENCES = [
        'coffee' => CoffeeAtlasExperience::class,
        'oncall' => OnCallExperience::class,
        'nightsky' => NightSkyExperience::class,
        'gravity' => GravityExperience::class,
        'poland' => PolandExperience::class,
        'ulam' => UlamExperience::class,
    ];

    public function run(): void
    {
        if (Role::query()->where('guard_name', 'api')->count() === 0) {
            $this->call(PermissionsSeeder::class);
        }
        // cmi5 AUs report to the built-in LRS; it needs its store access record
        if (!Access::query()->exists()) {
            $this->call(LrsSeeder::class);
        }
        $this->actAsAdmin();

        $keys = $this->selectedExperiences();
        $refresh = filter_var(env('DEMO_REFRESH', false), FILTER_VALIDATE_BOOLEAN);
        $reports = [];
        // db:seed unguards all models; the demo data goes through the same
        // mass-assignment rules as the admin API, so guard them again.
        $wasUnguarded = Model::isUnguarded();
        Model::reguard();
        try {
            foreach ($keys as $key) {
                $class = self::EXPERIENCES[$key];
                /** @var DemoExperience $experience */
                $experience = new $class($this->command);
                $reports[$experience->title()] = $experience->run($refresh);
            }
        } finally {
            if ($wasUnguarded) {
                Model::unguard();
            }
        }

        $this->printSummary($reports);
    }

    /** @return array<int, string> */
    private function selectedExperiences(): array
    {
        $value = (string) (env('DEMO_EXPERIENCE') ?: env('ULAMS_DEMO_EXPERIENCE') ?: 'all');
        $keys = array_filter(array_map('trim', explode(',', strtolower($value))));
        if (in_array('all', $keys, true)) {
            return array_keys(self::EXPERIENCES);
        }
        $unknown = array_diff($keys, array_keys(self::EXPERIENCES));
        if ($unknown) {
            throw new RuntimeException('Unknown DEMO_EXPERIENCE: ' . implode(', ', $unknown) . ' (use coffee, oncall, nightsky, gravity, poland, ulam or all)');
        }

        return array_values($keys);
    }

    /**
     * The repositories attach the current user as author and fire publish
     * events for them, so the seeder works as an administrator.
     */
    private function actAsAdmin(): void
    {
        $admin = User::query()->where('email', 'admin@ulams.app')->first()
            ?? User::role('admin', 'api')->orderBy('id')->first();
        if (!$admin) {
            $admin = app(UserServiceContract::class)->create(new UserSaveDto('Admin', 'Ulams', true, ['admin'], 'admin@ulams.app', null, 'secret', true));
        }
        Auth::setUser($admin);
        Auth::guard('api')->setUser($admin);
    }

    /** @param array<string, array<string, mixed>> $reports */
    private function printSummary(array $reports): void
    {
        $output = $this->command ? $this->command->getOutput() : null;
        if (!$output) {
            return;
        }
        $output->writeln('');
        $output->writeln('<info>Demo courses</info>');
        foreach ($reports as $title => $report) {
            $topics = $report['topics'];
            ksort($topics);
            if (isset($report['kept'])) {
                $output->writeln(sprintf('  #%d %s: kept, %d lessons, %d topics', $report['course_id'], $title, $report['kept'][0], $report['kept'][1]));
                $topics = null;
            }
            $topics === null || $output->writeln(sprintf(
                '  #%d %s: %d lessons, %d topics (%s)',
                $report['course_id'],
                $title,
                $report['lessons'],
                array_sum($topics),
                implode(', ', array_map(fn ($k, $v) => "$k $v", array_keys($topics), $topics))
            ));
            if ($report['questions']) {
                $output->writeln('     quiz questions: ' . implode(', ', array_map(fn ($k, $v) => "$k $v", array_keys($report['questions']), $report['questions'])));
            }
            foreach ($report['extras'] as $key => $value) {
                if ($key === 'h5p_contents') {
                    $value = 'H5P content ids ' . implode(', ', $value);
                }
                $output->writeln('     ' . $key . ': ' . (is_array($value) ? implode('; ', $value) : $value));
            }
            foreach ($report['skipped'] as $skipped) {
                $output->writeln('<comment>     skipped: ' . $skipped . '</comment>');
            }
        }
    }
}
