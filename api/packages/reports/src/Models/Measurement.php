<?php

namespace EscolaLms\Reports\Models;

use EscolaLms\Reports\Models\Report;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Measurement extends Model
{
    use HasFactory;

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
