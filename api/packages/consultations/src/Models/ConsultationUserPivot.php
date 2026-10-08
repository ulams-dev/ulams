<?php

namespace EscolaLms\Consultations\Models;

use EscolaLms\Consultations\Database\Factories\ConsultationUserFactory;
use EscolaLms\Consultations\Enum\ConsultationTermReminderStatusEnum;
use EscolaLms\Consultations\Enum\ConsultationTermStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property int $consultation_id
 * @property Consultation $consultation
 *
 * @property Carbon $executed_at
 * @property ConsultationTermReminderStatusEnum $reminder_status
 * @property ConsultationTermStatusEnum $executed_status
 */
class ConsultationUserPivot extends Model
{
    use HasFactory;

    protected $table = 'consultation_user';

    protected $fillable = [
        'user_id',
        'executed_at',
        'executed_status',
        'consultation_id',
        'product_id',
        'reminder_status',
        'finished_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id', 'users');
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /**
     * @deprecated
     */
    public function isApproved(): bool
    {
        return $this->executed_status->is(ConsultationTermStatusEnum::APPROVED);
    }

    /**
     * @deprecated
     */
    public function isRejected(): bool
    {
        return $this->executed_status->is(ConsultationTermStatusEnum::REJECT);
    }

    public function userTerms(): HasMany
    {
        return $this->hasMany(ConsultationUserTerm::class, 'consultation_user_id');
    }

    protected static function newFactory(): ConsultationUserFactory
    {
        return ConsultationUserFactory::new();
    }
}
