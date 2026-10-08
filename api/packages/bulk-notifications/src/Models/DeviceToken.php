<?php

namespace Ulams\BulkNotifications\Models;

use Ulams\BulkNotifications\Database\Factories\DeviceTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 *
 * Class DeviceToken
 *
 * @package Ulams\BulkNotifications\Models
 *
 * @property int $id
 * @property string $token
 * @property int $user_id
 * @property Carbon $created_at
 * @property Carbon $update_at
 *
 * @property User $user
 */
class DeviceToken extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): DeviceTokenFactory
    {
        return DeviceTokenFactory::new();
    }
}
