<?php

namespace Ulams\Reports\Models;

use Ulams\Core\Models\Concerns\HasMorphTypeColumns;
use Ulams\Reports\Models\Report;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Measurement extends Model
{
    use HasFactory;
    use HasMorphTypeColumns;

    /** @var array<int, string> polymorphic type columns, stored as morph aliases (see MorphMap) */
    protected $morphTypeColumns = ['measurable_type'];

    protected $guarded = ['id'];

    protected $casts = [];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function measurable(): MorphTo
    {
        return $this->morphTo();
    }
}
