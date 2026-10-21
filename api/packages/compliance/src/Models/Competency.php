<?php

namespace Ulams\Compliance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A node of the competency framework.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 */
class Competency extends Model
{
    protected $table = 'compliance_competencies';

    protected $guarded = ['id'];

    protected $casts = ['parent_id' => 'integer'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }
}
