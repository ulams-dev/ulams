<?php

namespace Ulams\Entitlements\Console;

use Illuminate\Console\Command;
use Ulams\Entitlements\Services\EntitlementService;

/** Marks entitlements whose `valid_until` has passed as expired and recomputes their `course_user` rows (ADR 0130). */
class ExpireCommand extends Command
{
    protected $signature = 'ulams:entitlements:expire';

    protected $description = 'Expire entitlements whose end date has passed';

    public function handle(EntitlementService $entitlements): int
    {
        $this->info("Expired {$entitlements->expireDue()} entitlement(s).");

        return self::SUCCESS;
    }
}
