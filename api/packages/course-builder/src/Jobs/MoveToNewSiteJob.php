<?php

namespace Ulams\CourseBuilder\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Site\NewSite;

/** Provisions a new site for a builder session and moves the session there (ADR 0048). */
class MoveToNewSiteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public readonly string $sessionId)
    {
    }

    public static function dispatchFor(string $sessionId): void
    {
        $job = new self($sessionId);
        if ($c = config('course_builder.queue_connection')) {
            $job->onConnection($c);
        }
        if ($q = config('course_builder.queue')) {
            $job->onQueue($q);
        }
        dispatch($job);
    }

    public function handle(NewSite $site): void
    {
        $session = Session::query()->find($this->sessionId);
        if ($session !== null) {
            $site->run($session);
        }
    }
}
