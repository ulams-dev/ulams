<?php

namespace EscolaLms\ConsultationAccess\Models;

use EscolaLms\ConsultationAccess\Database\Factories\ConsultationAccessEnquiryProposedTermFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm
 *
 * @property-read int $id
 * @property int $consultation_access_enquiry_id
 * @property Carbon $proposed_at
 *
 * @property-read ConsultationAccessEnquiry $consultationAccessEnquiry
 */
class ConsultationAccessEnquiryProposedTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'consultation_access_enquiry_id',
        'proposed_at',
    ];

    protected $casts = [
        'proposed_at' => 'datetime',
    ];

    public function consultationAccessEnquiry(): BelongsTo
    {
        return $this->belongsTo(ConsultationAccessEnquiry::class);
    }

    public static function newFactory(): ConsultationAccessEnquiryProposedTermFactory
    {
        return ConsultationAccessEnquiryProposedTermFactory::new();
    }
}
