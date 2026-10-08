<?php

namespace EscolaLms\TopicTypeGift\Http\Resources;

use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Support\Collection;

/**
 * @OA\Schema(
 *      schema="QuizAttemptResource",
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
 *     @OA\Property(
 *          property="questions",
 *          description="questions",
 *          type="array",
 *          @OA\Items(ref="#/components/schemas/GiftQuestionResource")
 *      ),
 *     @OA\Property(
 *          property="answers",
 *          description="answers",
 *          type="array",
 *          @OA\Items(ref="#/components/schemas/AttemptAnswerResource")
 *      )
 * )
 *
 */

/**
 * @mixin QuizAttempt
 */
class QuizAttemptResource extends QuizAttemptSimpleResource
{
    public function toArray($request): array
    {
        // When randomize_order is on, both the question order and the order of
        // options within each question are randomized, seeded with the attempt id
        // so the whole layout is random but stable for a given attempt.
        $optionsSeed = $this->giftQuiz->randomize_order ? $this->id : null;

        $questions = $this->orderedQuestions()->map(
            fn (GiftQuestion $question) => (new GiftQuestionResource($question))->withOptionsSeed($optionsSeed)
        )->values();

        return array_merge(parent::toArray($request), [
            'questions' => $questions,
            'answers' => AttemptAnswerResource::collection($this->answers),
        ]);
    }

    /**
     * Questions in the order they should be presented to the student.
     *
     * When the quiz has randomize_order enabled the questions are shuffled with
     * the attempt id used as the seed. Sorting by id first gives a deterministic
     * input order, so the same seed always yields the same permutation: the order
     * is random but stable for a given attempt (a page refresh returns the same
     * order), while different attempts (different seeds) may get different orders.
     * Otherwise the questions keep their configured `order`.
     */
    private function orderedQuestions(): Collection
    {
        if ($this->giftQuiz->randomize_order) {
            return $this->giftQuiz->questions->sortBy('id')->values()->shuffle($this->id);
        }

        return $this->giftQuiz->questions->sortBy('order')->values();
    }
}
