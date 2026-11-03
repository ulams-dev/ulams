<?php

namespace Ulams\Tenancy\Tests\Mocks;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;

/**
 * Runs the artisan command in this process, as the academy it was made for (its TENANT_SLUG set for the call), instead
 * of starting a child process. The test database stands in for both the platform's and that academy's; a call for
 * another academy's host is only recorded.
 */
class InProcessTenantRunner implements TenantCommandRunnerContract
{
    /** @var list<array{host: string, arguments: list<string>}> */
    public array $calls = [];

    public bool $fail = false;

    public function __construct(private string $slug = 'coffee')
    {
    }

    public function run(string $host, array $arguments): string
    {
        $this->calls[] = ['host' => $host, 'arguments' => $arguments];
        if ($this->fail) {
            throw new RuntimeException('the academy did not answer');
        }
        // another academy has a database of its own, which this one test database cannot stand in for: record only
        if ($host !== $this->slug . '.localhost') {
            return '';
        }

        $command = array_shift($arguments);
        $parameters = [];
        foreach ($arguments as $option) {
            [$name, $value] = explode('=', ltrim($option, '-'), 2);
            $parameters['--' . $name] = $value;
        }
        $before = config('ulams_tenancy.tenant_slug');
        config(['ulams_tenancy.tenant_slug' => $this->slug]);
        try {
            $status = Artisan::call($command, $parameters);
        } finally {
            config(['ulams_tenancy.tenant_slug' => $before]);
        }
        $output = Artisan::output();
        if ($status !== 0) {
            throw new RuntimeException("{$command} failed: {$output}");
        }

        return $output;
    }
}
