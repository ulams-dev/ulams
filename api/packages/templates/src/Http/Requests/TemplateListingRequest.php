<?php

namespace EscolaLms\Templates\Http\Requests;

use EscolaLms\Templates\Facades\Template as FacadesTemplate;
use EscolaLms\Templates\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TemplateListingRequest extends FormRequest
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
            'event' => ['sometimes', 'string', Rule::in($events)],
            'channel' => ['sometimes', 'string', Rule::in($channels)],
            'name' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'nullable', 'integer'],
            'order_by' => ['sometimes', 'nullable', 'string', 'in:id,created_at,name,event,default,channel'],
        ];
    }

    public function getPerPage(): ?int
    {
        return $this->input('per_page');
    }
}
