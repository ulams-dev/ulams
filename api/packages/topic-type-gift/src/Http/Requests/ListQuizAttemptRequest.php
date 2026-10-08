<?php

namespace EscolaLms\TopicTypeGift\Http\Requests;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\TopicTypeGift\Dtos\Criteria\PageDto;
use EscolaLms\TopicTypeGift\Dtos\Criteria\QuizAttemptCriteriaDto;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListQuizAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('listOwn', QuizAttempt::class);
    }

    public function rules(): array
    {
        return [
            'order_by' => ['sometimes', 'string', 'in:id,user_id,topic_gift_quiz_id,started_at,end_at,max_score,result_score,created_at'],
            'order' => ['sometimes', 'string', 'in:ASC,DESC'],
        ];
    }

    public function getCriteriaDto(): QuizAttemptCriteriaDto
    {
        return QuizAttemptCriteriaDto::instantiateFromRequest($this);
    }

    public function getPageDto(): PageDto
    {
        return PageDto::instantiateFromRequest($this);
    }

    public function getOrderDto(): OrderDto
    {
        return OrderDto::instantiateFromRequest($this);
    }
}
