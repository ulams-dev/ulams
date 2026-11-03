<?php

namespace App\Health;

use Illuminate\Support\Facades\Http;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * PDF and MJML renderers: an outage is a warning, not a failure (the LMS works without them, only
 * certificates and some e-mails wait), so the check passes and says so; `ulams_health_check_ok` and the
 * message show it. Spatie counts a warning as unhealthy, which would take the whole API out of rotation.
 */
class ServiceReachableCheck extends Check
{
    public function __construct(private readonly string $url = '', private readonly string $path = '/health')
    {
        parent::__construct();
    }

    public static function for(string $name, ?string $url, string $path = '/health'): ?self
    {
        $url = is_string($url) ? rtrim($url, '/') : '';
        if ($url === '' || !str_starts_with($url, 'http')) {
            return null;
        }

        return (new self($url, $path))->name($name)->label(strtoupper($name) . ' service');
    }

    public function run(): Result
    {
        $result = Result::make()->meta(['url' => $this->url]);
        try {
            $response = Http::timeout(2)->get($this->url . $this->path);
            if ($response->successful()) {
                return $result->ok();
            }
            $problem = 'answered HTTP ' . $response->status();
        } catch (\Throwable $e) {
            $problem = 'is unreachable';
        }

        return $result->ok("Warning: the service {$problem} (certificates and e-mails that need it wait)");
    }
}
