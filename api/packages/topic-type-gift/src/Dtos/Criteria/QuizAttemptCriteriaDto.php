<?php

namespace Ulams\TopicTypeGift\Dtos\Criteria;

use Ulams\Core\Dtos\Contracts\DtoContract;
use Ulams\Core\Dtos\Contracts\InstantiateFromRequest;
use Ulams\Core\Dtos\CriteriaDto as BaseCriteriaDto;
use Ulams\Core\Repositories\Criteria\Primitives\DateCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\EqualCriterion;
use Ulams\TopicTypeGift\Enum\TopicTypeGiftPermissionEnum;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Repositories\Criterion\RawCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class QuizAttemptCriteriaDto extends BaseCriteriaDto implements DtoContract, InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->get('user_id')) {
            $criteria->push(new EqualCriterion('user_id', $request->get('user_id')));
        }

        if ($request->get('topic_gift_quiz_id')) {
            $criteria->push(new EqualCriterion('topic_gift_quiz_id', $request->get('topic_gift_quiz_id')));
        }

        if ($request->get('date_from')) {
            $criteria->push(new DateCriterion('started_at', $request->get('date_from'), '>='));
        }

        if ($request->get('date_to')) {
            $criteria->push(new DateCriterion('end_at', $request->get('date_to'), '<='));
        }

        if ($request->get('course_id')) {
            $criteria->push(
                new RawCriterion('EXISTS (SELECT tgg.id
                                      FROM topic_gift_quizzes tgg
                                        JOIN topics t ON tgg.id = t.topicable_id
                                            JOIN lessons l ON t.lesson_id = l.id
                                      WHERE t.topicable_type = ? 
                                            AND l.course_id = ?
                                            AND topic_gift_quiz_attempts.topic_gift_quiz_id = tgg.id)',
                    [GiftQuiz::class, $request->get('course_id')]
                )
            );
        }

        if (
            !$request->user()->can(TopicTypeGiftPermissionEnum::LIST_QUIZ_ATTEMPT) &&
            $request->user()->can(TopicTypeGiftPermissionEnum::LIST_SELF_QUIZ_ATTEMPT)
        ) {
            $criteria->push(
                new RawCriterion('EXISTS (SELECT tgg.id
                                      FROM topic_gift_quizzes tgg
                                        JOIN topics t ON tgg.id = t.topicable_id
                                            JOIN lessons l ON t.lesson_id = l.id
                                                JOIN course_author ca ON l.course_id = ca.course_id
                                      WHERE t.topicable_type = ? 
                                            AND ca.author_id = ?
                                            AND topic_gift_quiz_attempts.topic_gift_quiz_id = tgg.id)',
                    [GiftQuiz::class, $request->user()->getKey()]
                )
            );
        }

        return new static($criteria);
    }
}
