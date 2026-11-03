<?php

namespace Ulams\TemplatesPdf\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Ulams\TemplatesPdf\Enums\PdfPermissionsEnum;

class CertificateRevokeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(PdfPermissionsEnum::PDF_REVOKE);
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }
}
