<?php

namespace Ulams\Compliance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "A user whose attributes match all conditions is enrolled in the path, due in N days."
 *
 * @property int $id
 * @property string $name
 * @property bool $active
 * @property list<array{attribute: string, operator: string, value?: mixed}> $conditions
 * @property int $path_id
 * @property int|null $due_days
 */
class Rule extends Model
{
    protected $table = 'compliance_rules';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean', 'conditions' => 'array', 'due_days' => 'integer'];

    public function path(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class, 'path_id');
    }
}
