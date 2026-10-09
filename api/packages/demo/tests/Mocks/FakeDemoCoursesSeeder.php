<?php

namespace Ulams\Demo\Tests\Mocks;

use Illuminate\Database\Seeder;

/**
 * Stands in for Database\Seeders\DemoCoursesSeeder: records the experience it was asked for.
 */
class FakeDemoCoursesSeeder extends Seeder
{
    public const EXPERIENCES = ['coffee' => true, 'oncall' => true];

    public static ?string $experience = null;

    public function run(): void
    {
        self::$experience = env('DEMO_EXPERIENCE');
    }
}
