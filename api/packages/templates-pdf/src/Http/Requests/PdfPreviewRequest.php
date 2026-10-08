<?php

namespace Ulams\TemplatesPdf\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Ulams\Templates\Enums\TemplatesPermissionsEnum;

/**
 * Preview of unsaved designer content: only template authors may render
 * arbitrary templates.
 */
class PdfPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->can(TemplatesPermissionsEnum::TEMPLATES_UPDATE) || $user->can(TemplatesPermissionsEnum::TEMPLATES_CREATE));
    }

    public function rules(): array
    {
        return [
            'event' => ['required', 'string'],
            'content' => ['required', function (string $attribute, mixed $value, $fail) {
                if (!is_array($value) && !(is_string($value) && is_array(json_decode($value, true)))) {
                    $fail('The content must be a pdfme template (object or JSON string).');
                }
            }],
        ];
    }

    public function getTemplateContent(): array|string
    {
        return $this->input('content');
    }
}
