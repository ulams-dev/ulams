<?php

namespace EscolaLms\Consultations\Models\Traits;

use EscolaLms\Consultations\Models\Consultation;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasConsultations
{
    public function consultations(): BelongsToMany
    {
        /* @var $this \EscolaLms\Core\Models\User */
        return $this->belongsToMany(Consultation::class, 'consultation_user')->withTimestamps();
    }
}
