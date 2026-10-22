<?php

namespace Ulams\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single sign-on provider of one tenant.
 *
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property string $protocol
 * @property bool $enabled
 * @property list<string>|null $email_domains
 * @property bool $trust_email
 * @property bool $jit_enabled
 * @property string $default_role
 * @property array{issuer?:string,client_id?:string,scopes?:list<string>,token_auth?:string,userinfo?:bool}|null $oidc
 * @property string|null $oidc_client_secret encrypted at rest, never serialised
 * @property string|null $oidc_secret_hint
 */
class IdentityProvider extends Model
{
    use HasUuids;

    public const OIDC = 'oidc';

    protected $table = 'identity_providers';

    protected $guarded = [];

    protected $hidden = ['oidc_client_secret'];

    protected $casts = [
        'enabled' => 'boolean',
        'trust_email' => 'boolean',
        'jit_enabled' => 'boolean',
        'email_domains' => 'array',
        'oidc' => 'array',
        'oidc_client_secret' => 'encrypted',
        'last_login_at' => 'datetime',
    ];

    public function links(): HasMany
    {
        return $this->hasMany(IdentityLink::class, 'provider_id');
    }

    /** Whether the e-mail address belongs to one of the organisation's domains of this provider. */
    public function ownsDomainOf(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));

        return $domain !== '' && in_array($domain, array_map('strtolower', (array) $this->email_domains), true);
    }
}
