<?php

namespace Ulams\Demo\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use Ulams\Demo\Tests\TestCase;

/**
 * The hourly reset is a scheduled artisan command: its flag must be rendered as `--force`,
 * not `--force=1` (Symfony rejects a value for an option that takes none).
 */
class ScheduledResetTest extends TestCase
{
    public function testResetIsScheduledWithABareForceFlag(): void
    {
        $commands = collect($this->app->make(Schedule::class)->events())
            ->map(fn ($event) => (string) $event->command)
            ->filter(fn (string $command) => Str::contains($command, 'ulams:demo:reset'));

        $this->assertCount(1, $commands);
        $command = $commands->first();
        $this->assertStringContainsString('--force', $command);
        $this->assertStringNotContainsString('--force=', $command);
    }
}
