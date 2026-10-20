<?php

namespace Ulams\LearnerInsights\Services;

use Illuminate\Support\Facades\DB;
use Ulams\Auth\Models\UserSetting;

/**
 * What a learner controls and may take with them (ADR 0061): the opt-out, the consent, the export of
 * everything stored about them, and erasure. Every export and erasure is written to the audit table
 * as counts only.
 */
class Privacy
{
    /** user setting key => default when unset */
    public const SETTINGS = ['enabled' => true, 'help' => true, 'nudges' => true];

    /** Tables that hold personal data, with the column that holds the user id. */
    private const PERSONAL_TABLES = ['learner_signals', 'learner_statuses', 'learner_status_transitions'];

    /** @return array{enabled:bool,help:bool,nudges:bool,consent:?string} */
    public function settings(int $userId): array
    {
        $rows = UserSetting::query()->where('user_id', $userId)->where('key', 'like', 'insights.%')->get()->pluck('value', 'key');
        $out = [];
        foreach (self::SETTINGS as $name => $default) {
            $out[$name] = isset($rows["insights.$name"]) ? $this->truthy($rows["insights.$name"]) : $default;
        }
        $out['consent'] = isset($rows['insights.consent']) && $this->truthy($rows['insights.consent']) ? (string) $rows['insights.consent'] : null;

        return $out;
    }

    /** @param array{enabled?:bool,help?:bool,nudges?:bool,consent?:bool} $changes */
    public function update(int $userId, array $changes): void
    {
        foreach (self::SETTINGS as $name => $default) {
            if (array_key_exists($name, $changes)) {
                UserSetting::query()->updateOrCreate(['user_id' => $userId, 'key' => "insights.$name"], ['value' => $changes[$name] ? 'true' : 'false']);
            }
        }
        if (array_key_exists('consent', $changes)) {
            if ($changes['consent']) {
                UserSetting::query()->updateOrCreate(['user_id' => $userId, 'key' => 'insights.consent'], ['value' => now()->toIso8601String()]);
            } else {
                UserSetting::query()->where('user_id', $userId)->where('key', 'insights.consent')->delete();
            }
        }
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    public function export(int $userId, ?int $actorId = null): array
    {
        $data = [];
        foreach (self::PERSONAL_TABLES as $table) {
            $data[$table] = DB::table($table)->where('user_id', $userId)->orderBy('id')->get()->map(function ($row) {
                $row = (array) $row;
                foreach (['data', 'reasons'] as $json) {
                    if (isset($row[$json]) && is_string($row[$json])) {
                        $row[$json] = json_decode($row[$json], true);
                    }
                }

                return $row;
            })->all();
        }
        $this->audit('export', $actorId ?? $userId, $userId, array_map('count', $data));

        return $data;
    }

    /**
     * Deletes the learner's signals, statuses and transitions. The opt-out and consent settings stay.
     *
     * @return array<string,int> rows deleted per table
     */
    public function erase(int $userId, ?int $actorId = null, string $action = 'erase'): array
    {
        $counts = [];
        foreach (self::PERSONAL_TABLES as $table) {
            $counts[$table] = DB::table($table)->where('user_id', $userId)->delete();
        }
        $this->audit($action, $actorId ?? ($action === 'erase' ? $userId : null), $userId, $counts);

        return $counts;
    }

    private function audit(string $action, ?int $actorId, int $subject, array $counts): void
    {
        DB::table('learner_insights_audit')->insert(['action' => $action, 'actor_id' => $actorId, 'subject_user_id' => $subject, 'counts' => json_encode($counts), 'created_at' => now()]);
    }

    private function truthy(mixed $value): bool
    {
        return !in_array(is_string($value) ? strtolower($value) : $value, ['false', '0', 'off', 'no', '', false, 0, null], true);
    }
}
