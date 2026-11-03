<?php

namespace Ulams\Auth\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $token_id
 * @property \Illuminate\Support\Carbon $day
 * @property int $requests
 * @property int $errors
 * @property int $rate_limited
 */
class ApiTokenUsage extends Model
{
    protected $table = 'api_token_usage_daily';

    protected $guarded = [];

    protected $casts = ['day' => 'date', 'requests' => 'integer', 'errors' => 'integer', 'rate_limited' => 'integer'];
}
