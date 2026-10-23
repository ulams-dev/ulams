<?php

namespace App\Health;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Ulams\Core\Observability\Heartbeat;

/**
 * The scheduler or a queue worker beat within the last five minutes (`ulams:heartbeat`, every minute).
 * Enforced only with HEALTH_REQUIRE_HEARTBEATS=true: a web pod must not leave the load balancer because a
 * worker is down, so probes keep it off and monitoring alerts on the check and on ulams_heartbeat_age_seconds.
 */
class HeartbeatCheck extends Check
{
    public static function for(string $name): self
    {
        return (new self())->name($name . '-heartbeat')->label(ucfirst($name) . ' heartbeat');
    }

    public function run(): Result
    {
        $kind = str_replace('-heartbeat', '', $this->getName());
        $age = Heartbeat::age($kind);
        $result = Result::make()->meta(['age_seconds' => $age]);

        if ($age !== null && $age <= Heartbeat::STALE_AFTER_SECONDS) {
            return $result->ok();
        }

        $message = $age === null
            ? "No {$kind} heartbeat recorded yet."
            : "The last {$kind} heartbeat was {$age} seconds ago.";

        return config('health.require_heartbeats', false)
            ? $result->failed($message)
            : $result->ok($message . ' (not enforced, HEALTH_REQUIRE_HEARTBEATS)');
    }
}
