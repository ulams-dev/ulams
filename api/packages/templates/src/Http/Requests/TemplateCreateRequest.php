<?php

namespace Ulams\Templates\Http\Requests;

use Ulams\Templates\Facades\Template as FacadesTemplate;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Rules\TemplateValidContentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TemplateCreateRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('create', Template::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('locale'))) {
            // `PL`, ` pl ` and an empty string (no language) are accepted the way an operator types them
            $locale = strtolower(trim($this->input('locale')));
            $this->merge(['locale' => $locale === '' ? null : $locale]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $channels = FacadesTemplate::getRegisteredChannels();
        $events = array_keys(FacadesTemplate::getRegisteredEvents());

        return [
            'name' => ['required', 'string'],
            'channel' => ['required', 'string', Rule::in($channels)],
            'event' => ['required', 'string', Rule::in($events)],
            'default' => ['sometimes', 'bool'],
            'locale' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z]{2,3}$/'],
            'sections' => ['required', 'array', new TemplateValidContentRule()],
            'sections.*.key' => ['string'],
            'sections.*.content' => ['string'],
        ];
    }
}
