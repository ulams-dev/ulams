<?php

namespace EscolaLms\Reports\Models;

use EscolaLms\Reports\Models\Measurement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [];

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }
}
