<?php

namespace EscolaLms\TopicTypeGift\Http\Resources;

use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="QuizAttemptSimpleResource",
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="user_id",
 *          description="user_id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="topic_gift_quiz_id",
 *          description="topic_gift_quiz_id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="started_at",
 *          description="started_at",
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="end_at",
 *          description="end_at",
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="is_ended",
 *          description="is_ended",
 *          type="boolean"
 *      ),
 *      @OA\Property(
 *          property="min_pass_score",
 *          description="min pass score",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="tutor_feedback",
 *          description="tutor feedback (only returned when the attempt is ended)",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="result_percent",
 *          description="result as percent of max score (null when not ended or no max)",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="is_passed",
 *          description="whether the attempt passed min_pass_score (null when not ended or no min_pass_score)",
 *          type="boolean"
 *      ),
 *      @OA\Property(
 *          property="correct_answers_count",
 *          description="number of answers with full score (null when not ended)",
 *          type="number"
 *      ),
 *     @OA\Property(
 *          property="user",
 *          description="user",
 *          type="object"
 *      ),
 *     @OA\Property(
 *          property="course",
 *          description="course",
 *          type="object"
 *      ),
 *      @OA\Property(
 *          property="topic",
 *          description="topic",
 *          type="object"
 *      ),
 * )
 *
 */

/**
 * @mixin QuizAttempt
 */
class QuizAttemptSimpleResource extends JsonResource
{
    public function toArray($request): array
    {
        $topic = $this->giftQuiz?->topic;
        $course = $topic?->lesson?->course;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'topic_gift_quiz_id' => $this->topic_gift_quiz_id,
            'started_at' => $this->started_at,
            'end_at' => $this->end_at,
            'max_score' => $this->max_score,
            'min_pass_score' => $this->giftQuiz->min_pass_score,
            'tutor_feedback' => $this->isEnded() ? $this->tutor_feedback : null,
            'result_score' => $this->result_score,
            'result_percent' => $this->result_percent,
            'is_passed' => $this->is_passed,
            'correct_answers_count' => $this->correct_answers_count,
            'is_ended' => $this->isEnded(),
            'user' => UserSimpleResource::make($this->user),
            'topic' => $topic ? TopicSimpleResource::make($topic) : null,
            'course' => $course ? CourseSimpleResource::make($course) : null,
        ];
    }
}
