<?php

namespace Ulams\Lti\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Ulams\Lti\Database\Factories\LtiToolFactory;

/**
 * Platform side: an external tool this tenant launches (GeoGebra, a coding sandbox, ...).
 *
 * @property int $id
 * @property string $name
 * @property string $client_id issued by us
 * @property string $deployment_id
 * @property string $oidc_login_url
 * @property string $launch_url
 * @property ?string $deep_linking_url
 * @property ?array $redirect_uris
 * @property ?string $jwks_url
 * @property ?string $public_key PEM
 * @property ?array $custom
 * @property bool $share_name
 * @property bool $share_email
 * @property bool $nrps_enabled the tool may read the course member list (NRPS)
 * @property bool $enabled
 */
class LtiTool extends Model
{
    use HasFactory;

    protected $table = 'lti_tools';

    protected $fillable = [
        'name', 'client_id', 'deployment_id', 'oidc_login_url', 'launch_url', 'deep_linking_url',
        'redirect_uris', 'jwks_url', 'public_key', 'custom', 'share_name', 'share_email', 'nrps_enabled', 'enabled',
    ];

    protected $casts = [
        'redirect_uris' => 'array',
        'custom' => 'array',
        'share_name' => 'boolean',
        'share_email' => 'boolean',
        'nrps_enabled' => 'boolean',
        'enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (LtiTool $tool) {
            $tool->client_id ??= (string) Str::uuid();
            $tool->deployment_id ??= (string) Str::uuid();
        });
    }

    /**
     * Redirect URIs the tool may ask us to post an id_token to.
     *
     * @return string[]
     */
    public function allowedRedirectUris(): array
    {
        return array_values(array_unique(array_filter([
            $this->launch_url,
            $this->deep_linking_url,
            ...($this->redirect_uris ?? []),
        ])));
    }

    protected static function newFactory(): LtiToolFactory
    {
        return LtiToolFactory::new();
    }
}
