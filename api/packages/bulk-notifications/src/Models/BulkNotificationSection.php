<?php

namespace Ulams\BulkNotifications\Models;

use Ulams\BulkNotifications\Database\Factories\BulkNotificationSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 *
 * Class BulkNotificationSection
 *
 * @package Ulams\BulkNotifications\Models
 *
 * @property int $id
 * @property string $key
 * @property string $value
 * @property Carbon $created_at
 * @property Carbon $update_at
 *
 */
class BulkNotificationSection extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    protected static function newFactory(): BulkNotificationSectionFactory
    {
        return BulkNotificationSectionFactory::new();
    }
}
