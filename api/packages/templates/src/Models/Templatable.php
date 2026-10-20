<?php

namespace Ulams\Templates\Models;

use Ulams\Core\Models\Concerns\HasMorphTypeColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Templatable extends Model
{
    use HasFactory;
    use HasMorphTypeColumns;

    /** @var array<int, string> polymorphic type columns, stored as morph aliases (see MorphMap) */
    protected $morphTypeColumns = ['templatable_type'];

    protected $table = 'templatables';

    protected $casts = [
        'id' => 'integer',
    ];

    protected $guarded = [
        'id'
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function templatable(): MorphTo
    {
        return $this->morphTo();
    }
}
