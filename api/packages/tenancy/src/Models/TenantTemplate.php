<?php

namespace Ulams\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A named starting point for new tenants. Platform database only. Applied when a tenant is created; changing a template
 * later never changes the tenants made from it.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property ?string $theme
 * @property ?string $accent
 * @property bool $demo
 * @property int $users demo students created with the tenant
 * @property ?array $env_overrides inheritable keys only
 */
class TenantTemplate extends Model
{
    protected $table = 'tenant_templates';

    protected $fillable = ['slug', 'name', 'theme', 'accent', 'demo', 'users', 'env_overrides'];

    protected $casts = ['demo' => 'boolean', 'users' => 'integer', 'env_overrides' => 'encrypted:array'];

    protected $hidden = ['env_overrides'];
}
