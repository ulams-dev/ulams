<?php

namespace Ulams\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A branded front host of one tenant (ADR 0195). Platform database only. It never grants access:
 * it changes what the front shows first and which theme it wears.
 *
 * @property string $id
 * @property int $tenant_id
 * @property string $host
 * @property string $key
 * @property string $kind academy|department|course|role
 * @property ?array $target {group_id}, {course_id} or {role}; null for academy
 * @property string $label
 * @property ?array $theme {preset, accent, logo_alt}
 * @property ?array $seo {canonical, index, title, description}
 * @property ?string $locale
 * @property string $status pending_dns|active|disabled
 * @property ?string $verification_token
 * @property ?\Illuminate\Support\Carbon $verified_at
 */
class EntryPoint extends Model
{
    public const ACADEMY = 'academy';
    public const DEPARTMENT = 'department';
    public const COURSE = 'course';
    public const ROLE = 'role';
    public const KINDS = [self::ACADEMY, self::DEPARTMENT, self::COURSE, self::ROLE];

    public const PENDING_DNS = 'pending_dns';
    public const ACTIVE = 'active';
    public const DISABLED = 'disabled';
    public const STATUSES = [self::PENDING_DNS, self::ACTIVE, self::DISABLED];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'tenant_entry_points';

    protected $fillable = ['id', 'tenant_id', 'host', 'key', 'kind', 'target', 'label', 'theme', 'seo', 'locale', 'status', 'verification_token', 'verified_at'];

    protected $casts = ['target' => 'array', 'theme' => 'array', 'seo' => 'array', 'verified_at' => 'datetime'];

    protected $hidden = ['verification_token'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
