<?php

namespace Ulams\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passport\Token;

/**
 * @property int $id
 * @property string $token_id
 * @property string $kind cli | agent | ci | integration
 * @property string|null $agent_name
 * @property string $created_via admin | cli | device
 * @property int|null $rate_limit_per_minute
 * @property list<string>|null $resource_ids ids of the resources (saved reports) the token is limited to; null: no limit
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property string|null $last_used_ip
 * @property-read Token $token
 */
class ApiTokenMeta extends Model
{
    public const KINDS = ['cli', 'agent', 'ci', 'integration'];

    public const CREATED_VIA = ['admin', 'cli', 'device'];

    protected $table = 'api_token_meta';

    protected $guarded = [];

    protected $casts = ['last_used_at' => 'datetime', 'rate_limit_per_minute' => 'integer', 'resource_ids' => 'array'];

    public function token(): BelongsTo
    {
        return $this->belongsTo(Token::class, 'token_id');
    }
}
