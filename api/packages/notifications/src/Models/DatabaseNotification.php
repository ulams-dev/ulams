<?php

namespace EscolaLms\Notifications\Models;

use EscolaLms\Notifications\Casts\DatabaseNotificationData;
use Illuminate\Notifications\DatabaseNotification as IlluminateDatabaseNotification;

/**
 * @OA\Schema(
 *      schema="Notification",
 *      @OA\Property(
 *          property="id",
 *          description="notification uuid",
 *          type="integer",
 *      ),
 *      @OA\Property(
 *          property="type",
 *          description="notification full classname",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="event",
 *          description="event full classname",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="notifiable_type",
 *          description="class representing User that got the notification",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="notifiable_id",
 *          description="id of User that got the notification",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="data",
 *          description="all notification data",
 *          type="object"
 *      ),
 *      @OA\Property(
 *          property="read_at",
 *          description="timestamp when notification was marked as read",
 *          type="datetime"
 *      ),
 * )
 */
class DatabaseNotification extends IlluminateDatabaseNotification
{
    protected $casts = [
        'data' => DatabaseNotificationData::class,
        'read_at' => 'datetime',
    ];
}
