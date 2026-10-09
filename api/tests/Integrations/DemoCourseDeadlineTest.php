<?php

namespace Tests\Integrations;

use Database\Seeders\Demo\CoffeeAtlasExperience;
use Database\Seeders\Demo\NightSkyExperience;
use Database\Seeders\Demo\OnCallExperience;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The demo courses promise lifetime access. `hours_to_complete` is a per-learner deadline (first
 * progress + N hours); once it passed, every progress write and the time-on-task ping answered
 * 403 "Deadline missed" to the demo student.
 */
class DemoCourseDeadlineTest extends TestCase
{
    public static function experiences(): array
    {
        return [
            'coffee' => [CoffeeAtlasExperience::class],
            'oncall' => [OnCallExperience::class],
            'nightsky' => [NightSkyExperience::class],
        ];
    }

    #[DataProvider('experiences')]
    public function testDemoCourseHasNoDeadline(string $class): void
    {
        $fields = (new ReflectionMethod($class, 'courseFields'))->invoke(new $class());

        $this->assertArrayHasKey('hours_to_complete', $fields);
        $this->assertNull($fields['hours_to_complete']);
        $this->assertArrayNotHasKey('active_to', $fields);
    }
}
