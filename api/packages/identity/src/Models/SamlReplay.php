<?php

namespace Ulams\Identity\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An assertion (or logout request) id that was accepted once; kept until it can no longer be valid, so the same
 * signed message cannot be replayed.
 *
 * @property int $id
 * @property string $provider_id
 * @property string $id_hash
 */
class SamlReplay extends Model
{
    public $timestamps = false;

    protected $table = 'saml_replays';

    protected $guarded = [];

    protected $casts = ['expires_at' => 'datetime'];
}
