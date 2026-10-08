<?php

namespace Ulams\Categories\Models\Traits;

use Ulams\Categories\Models\Category;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasInterests
{
    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }
}
