<?php

namespace EscolaLms\Templates\Http\Requests;

use EscolaLms\Templates\Facades\Template as FacadesTemplate;
use EscolaLms\Templates\Models\Template;
use EscolaLms\Templates\Rules\TemplateValidContentRule;
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
            'sections' => ['required', 'array', new TemplateValidContentRule()],
            'sections.*.key' => ['string'],
            'sections.*.content' => ['string'],
        ];
    }
}
