<?php

namespace EscolaLms\Recommender\Models;

use EscolaLms\Recommender\Database\Factories\TermAnalyticFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TermAnalytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_type',
        'model_id',
        'term',
        'count',
        'sum_attention',
        'sum_emotions_angry',
        'sum_emotions_disgusted',
        'sum_emotions_fearful',
        'sum_emotions_happy',
        'sum_emotions_neutral',
        'sum_emotions_sad',
        'sum_emotions_surprised',
        'avg_attention',
        'avg_emotions_angry',
        'avg_emotions_disgusted',
        'avg_emotions_fearful',
        'avg_emotions_happy',
        'avg_emotions_neutral',
        'avg_emotions_sad',
        'avg_emotions_surprised',
        'max_emotion',
        'max_emotion_value',
        'aggregated_frames_count',
        'last_frame_at',
        'meet_recording_id',
        'mean_predicted_rating',
        'satisfaction_status',
        'satisfaction_requested_at',
        'satisfaction_models',
    ];

    protected $casts = [
        'term' => 'datetime',
        'last_frame_at' => 'datetime',
        'satisfaction_models' => 'array',
    ];

    public function meetRecording(): BelongsTo
    {
        return $this->belongsTo(MeetRecording::class);
    }

    protected static function newFactory(): TermAnalyticFactory
    {
        return TermAnalyticFactory::new();
    }
}
