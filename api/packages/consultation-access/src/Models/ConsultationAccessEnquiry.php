<?php

namespace EscolaLms\ConsultationAccess\Models;

use EscolaLms\Auth\Models\User;
use EscolaLms\ConsultationAccess\Database\Factories\ConsultationAccessEnquiryFactory;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use EscolaLms\Consultations\Models\ConsultationUserTerm;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry
 *
 * @property-read int $id
 * @property int $consultation_id
 * @property int $user_id
 * @property string $status
 * @property string $description
 * @property int $consultation_user_id
 * @property string $meeting_link
 * @property ?string $meeting_link_type
 * @property ?string $title
 * @property ?string $related_type
 * @property ?int $related_id
 *
 * @property-read User $user
 * @property-read Consultation $consultation
 * @property-read Collection|ConsultationAccessEnquiryProposedTerm[] $consultationAccessEnquiryProposedTerms
 * @property-read ?ConsultationUserPivot $consultationUser
 * @property-read ?Model $related
 * @property-read ?ConsultationUserTerm $consultationUserTerm
 */
class ConsultationAccessEnquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'consultation_id',
        'user_id',
        'status',
        'description',
        'consultation_user_id',
        'meeting_link',
        'meeting_link_type',
        'title',
        'related_type',
        'related_id',
        'consultation_user_term_id',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consultationUser(): BelongsTo
    {
        return $this->belongsTo(ConsultationUserPivot::class);
    }

    public function consultationUserTerm(): BelongsTo
    {
        return $this->belongsTo(ConsultationUserTerm::class);
    }

    public function consultationAccessEnquiryProposedTerms(): HasMany
    {
        return $this->hasMany(ConsultationAccessEnquiryProposedTerm::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo('related');
    }

    public static function newFactory(): ConsultationAccessEnquiryFactory
    {
        return ConsultationAccessEnquiryFactory::new();
    }
}
