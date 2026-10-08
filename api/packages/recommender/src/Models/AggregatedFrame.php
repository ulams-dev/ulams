<?php

namespace EscolaLms\Recommender\Models;

use EscolaLms\Recommender\Database\Factories\AggregatedFrameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AggregatedFrame extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_type',
        'model_id',
        'term',
        'window_start',
        'window_end',
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
        'median_attention',
        'median_emotions_angry',
        'median_emotions_disgusted',
        'median_emotions_fearful',
        'median_emotions_happy',
        'median_emotions_neutral',
        'median_emotions_sad',
        'median_emotions_surprised',
        'send_at',
        'aggregated_at',
        'should_break',
        'break_confidence',
        'recommended_in_minutes',
        'reasoning',
        'algorithm',
        'processing_time_ms',
        'external_id',
        'max_emotion',
        'max_emotion_value',
        'meet_users_count',
        'term_analytic_id',
    ];

    protected $casts = [
        'term' => 'datetime',
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'send_at' => 'datetime',
        'aggregated_at' => 'datetime',
    ];

    protected static function newFactory(): AggregatedFrameFactory
    {
        return AggregatedFrameFactory::new();
    }
}
