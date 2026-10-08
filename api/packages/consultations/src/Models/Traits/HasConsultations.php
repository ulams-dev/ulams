<?php

namespace Ulams\Consultations\Models\Traits;

use Ulams\Consultations\Models\Consultation;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasConsultations
{
    public function consultations(): BelongsToMany
    {
        /* @var $this \Ulams\Core\Models\User */
        return $this->belongsToMany(Consultation::class, 'consultation_user')->withTimestamps();
    }
}
