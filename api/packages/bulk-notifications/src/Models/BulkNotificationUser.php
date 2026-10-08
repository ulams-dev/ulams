<?php

namespace EscolaLms\BulkNotifications\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 *
 * Class BulkNotificationUser
 *
 * @package EscolaLms\BulkNotifications\Models
 *
 * @property int $id
 * @property int $user_id
 * @property int $bulk_notification_id
 *
 * @property BulkNotification $bulkNotification
 * @property User $user
 *
 */
class BulkNotificationUser extends Pivot
{
    protected $guarded = ['id'];

    public function bulkNotification(): BelongsTo
    {
        return $this->belongsTo(BulkNotification::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
