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
 * @property array<string,mixed>|null $saml IdP entity id, endpoints, name id format, encryption and SHA-1 switches
 * @property list<array{pem:string,fingerprint:string,not_before:?string,not_after:?string,added_at:string,source:string}>|null $saml_idp_certs
 * @property string|null $saml_sp_key encrypted at rest, never serialised
 * @property string|null $saml_sp_cert
 * @property string|null $saml_sp_next_key encrypted at rest, never serialised
 * @property string|null $saml_sp_next_cert
 * @property array<string,string>|null $scim_group_roles display name of a SCIM group (lower case) => role granted to its members
 */
class IdentityProvider extends Model
{
    use HasUuids;

    public const OIDC = 'oidc';
    public const SAML = 'saml';

    protected $table = 'identity_providers';

    protected $guarded = [];

    protected $hidden = ['oidc_client_secret', 'saml_sp_key', 'saml_sp_next_key'];

    protected $casts = [
        'enabled' => 'boolean',
        'trust_email' => 'boolean',
        'jit_enabled' => 'boolean',
        'email_domains' => 'array',
        'oidc' => 'array',
        'oidc_client_secret' => 'encrypted',
        'saml' => 'array',
        'saml_idp_certs' => 'array',
        'scim_group_roles' => 'array',
        'saml_sp_key' => 'encrypted',
        'saml_sp_next_key' => 'encrypted',
        'metadata_refreshed_at' => 'datetime',
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
