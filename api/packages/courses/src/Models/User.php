<?php

namespace Ulams\Courses\Models;

use Ulams\Auth\Models\Traits\HasGroups;
use Ulams\Auth\Models\Traits\HasOnboardingStatus;
use Ulams\Auth\Models\Traits\UserHasSettings;
use Ulams\Auth\Models\User as AuthUser;
use Ulams\Categories\Models\Category;
use Ulams\Courses\Models\Traits\HasAuthoredCourses;
use Ulams\Courses\Models\Traits\HasCourses;
use Ulams\Courses\Tests\Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends AuthUser
{
    use HasCourses, HasAuthoredCourses, HasGroups, HasOnboardingStatus, UserHasSettings;

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_user');
    }

    protected function getTraitOwner(): self
    {
        return $this;
    }

    public static function newFactory()
    {
        return UserFactory::new();
    }
}
