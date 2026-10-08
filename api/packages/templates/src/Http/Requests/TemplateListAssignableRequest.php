<?php

namespace EscolaLms\Templates\Http\Requests;

use EscolaLms\Templates\Facades\Template as FacadesTemplate;
use EscolaLms\Templates\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TemplateListAssignableRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Gate::allows('list', Template::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $channels = FacadesTemplate::getRegisteredChannels();
        $events = array_keys(FacadesTemplate::getRegisteredEvents());
        return [
            'assignable_class' => ['sometimes', 'string'],
            'event' => ['sometimes', 'string', Rule::in($events)],
            'channel' => ['sometimes', 'string', Rule::in($channels)],
        ];
    }
}
