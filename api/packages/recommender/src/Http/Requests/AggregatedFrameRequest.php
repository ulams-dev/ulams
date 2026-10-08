<?php

namespace EscolaLms\Recommender\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AggregatedFrameRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'model_type' => ['required'],
            'model_id' => ['required'],
            'term' => ['required'],
            'window_start' => ['required'],
            'window_end' => ['required'],
            'count' => ['required'],
            'sum_attention' => ['required', 'numeric'],
            'sum_emotions_angry' => ['required', 'numeric'],
            'sum_emotions_disgusted' => ['required', 'numeric'],
            'sum_emotions_fearful' => ['required', 'numeric'],
            'sum_emotions_happy' => ['required', 'numeric'],
            'sum_emotions_neutral' => ['required', 'numeric'],
            'sum_emotions_sad' => ['required', 'numeric'],
            'sum_emotions_surprised' => ['required', 'numeric'],
            'avg_attention' => ['required', 'numeric'],
            'avg_emotions_angry' => ['required', 'numeric'],
            'avg_emotions_disgusted' => ['required', 'numeric'],
            'avg_emotions_fearful' => ['required', 'numeric'],
            'avg_emotions_happy' => ['required', 'numeric'],
            'avg_emotions_neutral' => ['required', 'numeric'],
            'avg_emotions_sad' => ['required', 'numeric'],
            'avg_emotions_surprised' => ['required', 'numeric'],
            'median_attention' => ['required', 'numeric'],
            'median_emotions_angry' => ['required', 'numeric'],
            'median_emotions_disgusted' => ['required', 'numeric'],
            'median_emotions_fearful' => ['required', 'numeric'],
            'median_emotions_happy' => ['required', 'numeric'],
            'median_emotions_neutral' => ['required', 'numeric'],
            'median_emotions_sad' => ['required', 'numeric'],
            'median_emotions_surprised' => ['required', 'numeric'],
            'aggregated_at' => ['required'],
            'should_break' => ['nullable', 'boolean'],
            'break_confidence' => ['nullable', 'numeric'],
            'recommended_in_minutes' => ['nullable'],
            'reasoning' => ['nullable'],
            'algorithm' => ['nullable'],
            'processing_time_ms' => ['nullable'],
            'external_id' => ['required'],
        ];
    }
}
