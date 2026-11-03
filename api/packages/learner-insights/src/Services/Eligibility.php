<?php

namespace Ulams\LearnerInsights\Services;

use Ulams\Auth\Models\UserSetting;

/**
 * Whether signals about a learner may be recorded at all (ADR 0061): the module is on for the
 * tenant, the learner has not opted out, and, in consent mode, has consented.
 */
class Eligibility
{
    public function moduleEnabled(): bool
    {
        return (bool) config('learner_insights.enabled');
    }

    public function allowed(int $userId): bool
    {
        if (!$this->moduleEnabled()) {
            return false;
        }
        if ($this->setting($userId, 'insights.enabled') === false) {
            return false;
        }
        if (config('learner_insights.require_consent') && !$this->setting($userId, 'insights.consent')) {
            return false;
        }

        return true;
    }

    /** A stored user setting; null when unset. "false", "0", "off" and "no" read as false. */
    private function setting(int $userId, string $key): mixed
    {
        $row = UserSetting::query()->where('user_id', $userId)->where('key', $key)->first();
        if (!$row) {
            return null;
        }
        $value = $row->value;
        if (is_string($value) && in_array(strtolower($value), ['false', '0', 'off', 'no', ''], true)) {
            return false;
        }

        return $value === false || $value === 0 || $value === null ? false : $value;
    }
}
