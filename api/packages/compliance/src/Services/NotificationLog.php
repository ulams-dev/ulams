<?php

namespace Ulams\Compliance\Services;

use Illuminate\Support\Facades\DB;

/**
 * Idempotency and audit of what was sent: `once()` runs the sender only the first time a
 * (kind, subject, offset, recipient) combination is seen.
 */
class NotificationLog
{
    public const TABLE = 'compliance_notification_log';

    public function once(string $kind, string $subjectType, int $subjectId, int $offsetDays, int $userId, callable $send): bool
    {
        $inserted = DB::table(self::TABLE)->insertOrIgnore([
            'kind' => $kind,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'offset_days' => $offsetDays,
            'user_id' => $userId,
            'sent_at' => now(),
        ]);
        if (!$inserted) {
            return false;
        }
        $send();

        return true;
    }
}
