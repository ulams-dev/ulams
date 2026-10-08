<?php

namespace Ulams\Consultations\Models;

use Ulams\Auth\Models\User as AuthUser;
use Ulams\Categories\Models\Category;
use Ulams\Consultations\Models\Traits\HasConsultations;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends AuthUser
{
    use HasConsultations;

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_user');
    }
}
