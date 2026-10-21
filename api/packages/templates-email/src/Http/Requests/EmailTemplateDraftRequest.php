<?php

namespace Ulams\TemplatesEmail\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Ulams\Templates\Facades\Template as FacadesTemplate;
use Ulams\Templates\Models\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Services\EmailDraftRenderer;

class EmailTemplateDraftRequest extends FormRequest
{
    public const MAX_CONTENT_BYTES = 512 * 1024;

    public function authorize(): bool
    {
        return Gate::allows('update', Template::class);
    }

    public function rules(): array
    {
        $events = array_keys(array_filter(
            FacadesTemplate::getRegisteredEvents(),
            fn (array $channels) => array_key_exists(EmailChannel::class, $channels)
        ));

        return [
            'event' => ['required', 'string', Rule::in($events)],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', function ($attribute, $value, $fail) {
                if (is_string($value) && strlen($value) > self::MAX_CONTENT_BYTES) {
                    $fail(__('The content may not be larger than 512 KiB.'));
                }
            }],
            'sample_overrides' => ['sometimes', 'array'],
            'sample_overrides.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('event') || !is_array($this->input('sample_overrides'))) {
                return;
            }
            $allowed = app(EmailDraftRenderer::class)->overridableVariables($this->input('event'), $this->user());
            foreach (array_keys($this->input('sample_overrides')) as $name) {
                if (!in_array($name, $allowed, true)) {
                    $validator->errors()->add('sample_overrides', __('Unknown variable :name for this event.', ['name' => $name]));
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function sampleOverrides(): array
    {
        return array_map(fn ($v) => (string) $v, $this->validated()['sample_overrides'] ?? []);
    }
}
