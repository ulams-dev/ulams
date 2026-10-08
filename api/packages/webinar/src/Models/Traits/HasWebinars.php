<?php

namespace Ulams\Webinar\Models\Traits;

use Ulams\Webinar\Models\Webinar;
use Ulams\Webinar\Models\WebinarUserPivot;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasWebinars
{
    public function webinars(): BelongsToMany
    {
        /* @var $this \Ulams\Core\Models\User */
        return $this->belongsToMany(Webinar::class, 'webinar_user')->using(WebinarUserPivot::class);
    }
}
