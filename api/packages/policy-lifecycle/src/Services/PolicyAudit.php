<?php

namespace Ulams\PolicyLifecycle\Services;

use Ulams\LivingCourse\Services\AuditLog;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyVersion;

/**
 * Writes the policy's state changes to the Living Course audit chain (ADR 0034, 0140), so one export and one
 * verification cover course updates and policy decisions. `data` never holds file contents or personal data beyond ids.
 */
final class PolicyAudit
{
    public function __construct(private readonly AuditLog $audit)
    {
    }

    /** @param array<string,mixed> $data */
    public function record(string $action, Policy $policy, ?PolicyVersion $version = null, array $data = [], ?string $sessionId = null): void
    {
        $this->audit->record($action, [
            'subject_type' => 'policy',
            'subject_id' => $policy->id,
            'session_id' => $sessionId,
            'version_to' => $version?->number,
            'data' => ['policy_id' => $policy->id, 'title' => $policy->title] + ($version !== null ? ['version' => $version->number] : []) + $data,
        ]);
    }
}
