<?php

namespace EscolaLms\Categories\Models\Traits;

use EscolaLms\Categories\Models\Category;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasInterests
{
    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }
}
