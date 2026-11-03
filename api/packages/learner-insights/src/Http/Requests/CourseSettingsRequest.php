<?php

namespace Ulams\LearnerInsights\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Ulams\LearnerInsights\Enums\LearnerInsightsPermissionsEnum;

class CourseSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(LearnerInsightsPermissionsEnum::LEARNER_INSIGHTS_MANAGE, 'api');
    }

    /** Only rules and parameters that exist in the package defaults are accepted. */
    public function rules(): array
    {
        $rules = ['rules' => ['required', 'array']];
        foreach ((array) config('learner_insights.rules') as $rule => $params) {
            $rules["rules.$rule"] = ['sometimes', 'array'];
            foreach ($params as $param => $default) {
                $rules["rules.$rule.$param"] = $param === 'enabled'
                    ? ['sometimes', 'boolean']
                    : ['sometimes', 'numeric', 'min:1', 'max:3650'];
            }
        }

        return $rules;
    }
}
