<?php

namespace Ulams\Lti\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Ulams\Lti\Database\Factories\LtiPlatformFactory;

/**
 * Tool side: an LMS (Moodle, Canvas, ...) that launches our courses.
 *
 * @property int $id
 * @property string $name
 * @property string $issuer
 * @property string $client_id issued by the platform
 * @property array $deployment_ids
 * @property string $auth_login_url
 * @property string $auth_token_url
 * @property ?string $auth_server
 * @property string $jwks_url
 * @property ?int $default_course_id
 * @property bool $enabled
 */
class LtiPlatform extends Model
{
    use HasFactory;

    protected $table = 'lti_platforms';

    protected $fillable = [
        'name', 'issuer', 'client_id', 'deployment_ids', 'auth_login_url', 'auth_token_url',
        'auth_server', 'jwks_url', 'default_course_id', 'enabled',
    ];

    protected $casts = [
        'deployment_ids' => 'array',
        'enabled' => 'boolean',
    ];

    protected static function newFactory(): LtiPlatformFactory
    {
        return LtiPlatformFactory::new();
    }
}
