<?php

namespace EscolaLms\Recommender\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="TermAnalytic",
 *      @OA\Property(
 *          property="term",
 *          description="term",
 *          type="datetime",
 *      ),
 *      @OA\Property(
 *          property="attention",
 *          description="attention",
 *          type="numeric",
 *      ),
 *      @OA\Property(
 *          property="max_emotion",
 *          description="max_emotion",
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="max_emotion_percentage",
 *          description="max_emotion_percentage",
 *          type="numeric",
 *      ),
 *      @OA\Property(
 *           property="avg_angry",
 *           description="avg_angry",
 *           type="numeric",
 *       ),
 *     @OA\Property(
 *            property="avg_disgusted",
 *            description="avg_disgusted",
 *            type="numeric",
 *        ),
 *     @OA\Property(
 *            property="avg_fearful",
 *            description="avg_fearful",
 *            type="numeric",
 *        ),
 *     @OA\Property(
 *            property="avg_happy",
 *            description="avg_happy",
 *            type="numeric",
 *        ),
 *     @OA\Property(
 *            property="avg_neutral",
 *            description="avg_neutral",
 *            type="numeric",
 *        ),
 *     @OA\Property(
 *            property="avg_sad",
 *            description="avg_sad",
 *            type="numeric",
 *        ),
 *     @OA\Property(
 *            property="avg_surprised",
 *            description="avg_surprised",
 *            type="numeric",
 *        ),
 *     @OA\Property(
 *             property="model_id",
 *             description="model_id",
 *             type="numeric",
 *         ),
 *     @OA\Property(
 *             property="model_type",
 *             description="model_type",
 *             type="string",
 *         ),
 *     @OA\Property(
 *             property="model_name",
 *             description="model_name",
 *             type="string",
 *         ),
 *     @OA\Property(
 *              property="rating",
 *              description="Model term users rating",
 *              type="string",
 *          ),
 * )
 */
class TermAnalyticResource extends JsonResource
{
    public function toArray($request): array
    {
        // Format every rating with 5 decimal places, matching the mean_predicted_rating
        // decimal(10,5) column, so the scalar "rating" and the per-model "ratings" are identical.
        $formatRating = fn ($value) => $value === null ? null : number_format((float) $value, 5, '.', '');

        return [
            'id' => $this->resource->getKey(),
            'term' => $this->resource->term,
            'model_id' => $this->resource->model_id,
            'model_type' => $this->resource->model_type,
            'model_name' => $this->resource->model_name,
            'avg_attention' => $this->resource->avg_attention,
            'avg_emotions_angry' => $this->resource->avg_emotions_angry,
            'avg_emotions_disgusted' => $this->resource->avg_emotions_disgusted,
            'avg_emotions_fearful' => $this->resource->avg_emotions_fearful,
            'avg_emotions_happy' => $this->resource->avg_emotions_happy,
            'avg_emotions_neutral' => $this->resource->avg_emotions_neutral,
            'avg_emotions_sad' => $this->resource->avg_emotions_sad,
            'avg_emotions_surprised' => $this->resource->avg_emotions_surprised,
            'max_emotion' => $this->resource->max_emotion,
            'max_emotion_value' => $this->resource->max_emotion_value,
            'rating' => $formatRating($this->resource->mean_predicted_rating ?? null),
            'ratings' => collect($this->resource->satisfaction_models ?? [])
                ->map(function ($entry) use ($formatRating) {
                    if (is_array($entry) && array_key_exists('mean_predicted_rating', $entry)) {
                        $entry['mean_predicted_rating'] = $formatRating($entry['mean_predicted_rating']);
                    }
                    return $entry;
                })
                ->all(),
            'url' => $this->resource->meetRecording && $this->resource->meetRecording->is_url_valid ? $this->resource->meetRecording->url : null,
            'url_expires_at' => $this->resource->meetRecording->is_url_valid ? $this->resource->meetRecording->url_expires_at : null,
            'start_at' => $this->resource->meetRecording->start_at ?? null,
            'end_at' => $this->resource->meetRecording->end_at ?? null,
            'processing_video' => $this->resource->meetRecording->processing_video,
        ];
    }
}
