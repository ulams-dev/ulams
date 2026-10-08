<?php

namespace EscolaLms\Consultations\Models;

use EscolaLms\Consultations\Database\Factories\ConsultationUserTermFactory;
use EscolaLms\Consultations\Enum\ConsultationTermReminderStatusEnum;
use EscolaLms\Consultations\Enum\ConsultationTermStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ConsultationTermStatusEnum $executed_status
 * @property int $consultation_user_id
 * @property Carbon $executed_at
 * @property ConsultationUserPivot $consultationUser
 * @property ConsultationTermReminderStatusEnum $reminder_status
 * @property Carbon $finished_at
 */
class ConsultationUserTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'consultation_user_id',
        'executed_at',
        'executed_status',
        'reminder_status',
        'finished_at',
    ];

    public function consultationUser(): BelongsTo
    {
        return $this->belongsTo(ConsultationUserPivot::class, 'consultation_user_id', 'id', 'consultation_user');
    }

    protected static function newFactory(): ConsultationUserTermFactory
    {
        return ConsultationUserTermFactory::new();
    }
}
