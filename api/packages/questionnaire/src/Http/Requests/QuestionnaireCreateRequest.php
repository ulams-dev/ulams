<?php

namespace Ulams\Questionnaire\Http\Requests;

use Ulams\Questionnaire\Enums\QuestionnaireTargetGroupEnum;
use Ulams\Questionnaire\Models\Questionnaire;
use Ulams\Questionnaire\Models\QuestionnaireModelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class QuestionnaireCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Questionnaire::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['string', 'required'],
            'active' => 'boolean',
            'models' => ['sometimes', 'array'],
            'models.*' => ['sometimes', 'array'],
            'models.*.model_type_id' => ['integer', Rule::exists(QuestionnaireModelType::class, 'id')],
            'models.*.model_id' => ['integer'],
            'models.*.target_group' => ['nullable', Rule::in(QuestionnaireTargetGroupEnum::getValues())],
            'models.*.display_frequency_minutes' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
