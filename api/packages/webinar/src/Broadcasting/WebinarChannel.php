<?php

namespace Ulams\Webinar\Broadcasting;

use Ulams\Webinar\Models\Webinar;
use Illuminate\Contracts\Auth\Authenticatable;

class WebinarChannel
{
    public function join(Authenticatable $user, Webinar $webinar, string $term): bool
    {
        return $webinar->trainers()->where('users.id', '=', $user->getKey())->exists();
    }
}
