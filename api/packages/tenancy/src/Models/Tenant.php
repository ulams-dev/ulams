<?php

namespace Ulams\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A provisioned tenant. Stored in the platform database only.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property ?string $theme
 * @property ?string $accent
 * @property bool $demo
 * @property string $api_host
 * @property string $front_host
 * @property string $admin_host
 * @property string $db_name
 * @property string $db_user
 * @property string $db_password
 * @property string $app_key
 * @property ?string $passport_private_key
 * @property ?string $passport_public_key
 * @property string $bucket
 * @property string $redis_prefix
 * @property string $status
 * @property ?array $steps
 * @property ?string $last_error
 */
class Tenant extends Model
{
    public const STATUS_PROVISIONING = 'provisioning';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FAILED = 'failed';

    protected $table = 'tenants';

    protected $fillable = [
        'slug',
        'name',
        'theme',
        'accent',
        'demo',
        'api_host',
        'front_host',
        'admin_host',
        'db_name',
        'db_user',
        'db_password',
        'app_key',
        'passport_private_key',
        'passport_public_key',
        'bucket',
        'redis_prefix',
        'status',
        'steps',
        'last_error',
    ];

    protected $casts = [
        'db_password' => 'encrypted',
        'app_key' => 'encrypted',
        'passport_private_key' => 'encrypted',
        'passport_public_key' => 'encrypted',
        'steps' => 'array',
        'demo' => 'boolean',
    ];

    protected $hidden = [
        'db_password',
        'app_key',
        'passport_private_key',
        'passport_public_key',
    ];

    public function hasCompleted(string $step): bool
    {
        return isset(($this->steps ?? [])[$step]);
    }

    public function markCompleted(string $step): void
    {
        $steps = $this->steps ?? [];
        $steps[$step] = now()->toIso8601String();
        $this->steps = $steps;
        $this->save();
    }

    public function forget(string ...$steps): void
    {
        $this->steps = array_diff_key($this->steps ?? [], array_flip($steps));
    }

    public function apiUrl(): string
    {
        return config('ulams_tenancy.scheme', 'http') . '://' . $this->api_host;
    }

    public function frontUrl(): string
    {
        return config('ulams_tenancy.scheme', 'http') . '://' . $this->front_host;
    }

    public function adminUrl(): string
    {
        return config('ulams_tenancy.scheme', 'http') . '://' . $this->admin_host;
    }
}
