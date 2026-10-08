<?php

namespace Ulams\Questionnaire\Http\Requests;

use Ulams\Questionnaire\Dtos\QuestionnaireModelDto;
use Ulams\Questionnaire\Models\Questionnaire;
use Ulams\Questionnaire\Models\QuestionnaireModelType;
use Ulams\Questionnaire\Rules\ClassExist;
use Ulams\Questionnaire\Rules\ModelExist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class QuestionnaireExportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge([
            'id' => $this->getParamId(),
            'model_type_title' => $this->getModelTypeTitle(),
            'model_id' => $this->getModelId(),
        ]);
    }

    public function authorize(): bool
    {
        $questionnaire = $this->getQuestionnaire();

        return Gate::allows('update', $questionnaire);
    }

    public function rules(): array
    {
        return [
            'id' => [
                'integer',
                'required',
                Rule::exists(Questionnaire::class, 'id'),
            ],
            'model_type_title' => [
                'string',
                Rule::exists(QuestionnaireModelType::class, 'title'),
                new ClassExist
            ],
            'model_id' => [
                'integer',
                new ModelExist($this->input('model_type_title'), 'id'),
            ],
        ];
    }

    public function getParamId(): int
    {
        /** @var int */
        return $this->route('id');
    }

    public function getModelTypeTitle(): string
    {
        /** @var string */
        return $this->route('model_type_title');
    }

    public function getModelId(): int
    {
        /** @var int */
        return $this->route('model_id');
    }

    public function getQuestionnaire(): Questionnaire
    {
        return Questionnaire::findOrFail($this->getParamId());
    }

    public function getQuestionnaireModelType(): QuestionnaireModelType
    {
        /** @var QuestionnaireModelType */
        return QuestionnaireModelType::query()
            ->where('title', $this->getModelTypeTitle())
            ->firstOrFail();
    }

    public function toDto(): QuestionnaireModelDto
    {
        return QuestionnaireModelDto::instantiateFromRequest($this);
    }
}
