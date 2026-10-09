<?php

namespace Ulams\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $directive effective directive, e.g. `frame-src`
 * @property string $blocked_host host of the blocked resource, or `inline`, `eval`, `data`, `blob`, `self`
 * @property string $document_path path of the page, without query string
 * @property int $count
 * @property \Illuminate\Support\Carbon $first_seen_at
 * @property \Illuminate\Support\Carbon $last_seen_at
 */
class CspReport extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'count' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];
}
