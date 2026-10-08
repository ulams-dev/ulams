<?php

namespace Ulams\Lrs\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A document of the xAPI document resources (state, activity profile, agent profile).
 *
 * `data` is `{"content": ..., "type": "<content type>"}`; JSON content is stored decoded.
 *
 * @property int $id
 * @property object $data
 * @property string $timestamp
 * @property int|null $owner_id
 * @property \Illuminate\Support\Carbon $updated_at
 */
abstract class XapiDocument extends Model
{
    protected $casts = [
        'data' => 'object',
    ];

    protected $guarded = ['id'];

    /**
     * The column that holds the document id (`state_id` or `profile_id`).
     */
    abstract public static function documentIdColumn(): string;
}
