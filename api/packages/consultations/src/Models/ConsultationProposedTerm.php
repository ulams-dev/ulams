<?php

namespace EscolaLms\Consultations\Models;

use EscolaLms\Consultations\Database\Factories\ConsultationProposedTermFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $proposed_at
 */
class ConsultationProposedTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'consultation_id',
        'proposed_at',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class, 'consultation_id');
    }

    protected static function newFactory(): ConsultationProposedTermFactory
    {
        return ConsultationProposedTermFactory::new();
    }
}
