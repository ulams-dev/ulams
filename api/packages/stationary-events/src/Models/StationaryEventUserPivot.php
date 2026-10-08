<?php

namespace EscolaLms\StationaryEvents\Models;

use EscolaLms\Auth\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class StationaryEventUserPivot extends Pivot
{
    const TABLE_NAME = 'stationary_event_users';
    protected $table = self::TABLE_NAME;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stationaryEvent(): BelongsTo
    {
        return $this->belongsTo(StationaryEvent::class);
    }
}
