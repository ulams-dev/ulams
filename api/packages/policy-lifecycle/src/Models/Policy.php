<?php

namespace Ulams\PolicyLifecycle\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A policy the organisation trains on: an owner, versions (uploaded PDFs) and one variant per department.
 *
 * @property string $id
 * @property string $title
 * @property int $owner_user_id
 * @property string|null $current_version_id the approved version learners are trained on
 * @property int $minor_due_days
 * @property int $major_due_days
 * @property array $brief
 * @property string $status active | archived
 */
class Policy extends Model
{
    use HasUlids;

    public const ACTIVE = 'active';
    public const ARCHIVED = 'archived';

    protected $table = 'policies';

    protected $guarded = [];

    protected $casts = ['brief' => 'array', 'owner_user_id' => 'integer', 'minor_due_days' => 'integer', 'major_due_days' => 'integer'];

    public function versions(): HasMany
    {
        return $this->hasMany(PolicyVersion::class, 'policy_id')->orderBy('number');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(PolicyVariant::class, 'policy_id')->orderBy('position')->orderBy('created_at');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class, 'current_version_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'owner_user_id');
    }
}
