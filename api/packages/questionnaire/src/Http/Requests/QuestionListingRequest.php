<?php

namespace Ulams\Questionnaire\Http\Requests;

use Ulams\Questionnaire\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class QuestionListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', Question::class);
    }

    public function rules(): array
    {
        return [
            'order_by' => ['string', 'in:id,description,title,questionnaire_id,active,type,position'],
            'order' => ['string', 'in:ASC,DESC'],
        ];
    }
}
