<?php

namespace Ulams\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $provider_id
 * @property int $user_id
 * @property string $subject
 * @property string|null $email_at_link
 * @property string $linked_via
 */
class IdentityLink extends Model
{
    public const JIT = 'jit';
    public const EMAIL_TRUST = 'email_trust';

    protected $table = 'identity_links';

    protected $guarded = [];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(IdentityProvider::class, 'provider_id');
    }
}
