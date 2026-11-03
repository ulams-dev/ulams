<?php

namespace Ulams\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "This tenant was created by that tenant for this purpose." One row per (parent, purpose, reference): asking again for
 * the same reference finds the tenant that exists. Kept after the tenant is deleted, with status `deleted`, as the record.
 *
 * @property int $id
 * @property string $tenant_slug
 * @property string $parent_slug
 * @property ?string $template_slug
 * @property string $purpose
 * @property string $reference
 * @property ?string $requested_by
 * @property string $status active | deleted
 * @property ?\Illuminate\Support\Carbon $deleted_at
 */
class TenantDerivation extends Model
{
    public const ACTIVE = 'active';
    public const DELETED = 'deleted';

    protected $table = 'tenant_derivations';

    protected $fillable = ['tenant_slug', 'parent_slug', 'template_slug', 'purpose', 'reference', 'requested_by', 'status', 'deleted_at'];

    protected $casts = ['deleted_at' => 'datetime'];
}
