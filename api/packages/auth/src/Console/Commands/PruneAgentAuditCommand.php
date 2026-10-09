<?php

namespace Ulams\Auth\Console\Commands;

use Illuminate\Console\Command;
use Ulams\Auth\Models\AgentAuditLog;
use Ulams\Auth\UlamsAuthServiceProvider;

class PruneAgentAuditCommand extends Command
{
    protected $signature = 'ulams:auth:prune-agent-audit {--days= : Keep this many days (default: ulams_auth.agent_audit_days)}';

    protected $description = 'Delete agent audit log rows older than the retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config(UlamsAuthServiceProvider::CONFIG_KEY . '.agent_audit_days', 365));
        $deleted = AgentAuditLog::query()->where('created_at', '<', now()->subDays(max(1, $days)))->delete();
        $this->info("Deleted {$deleted} audit rows older than {$days} days.");

        return self::SUCCESS;
    }
}
