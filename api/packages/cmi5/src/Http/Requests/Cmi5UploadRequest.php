<?php

namespace EscolaLms\Cmi5\Http\Requests;

use EscolaLms\Cmi5\Models\Cmi5;
use EscolaLms\Cmi5\Rules\Cmi5Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class Cmi5UploadRequest extends FormRequest
{
    public function authorize(): bool
    {
         return Gate::allows('upload', Cmi5::class);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:zip', new Cmi5Rule()]
        ];
    }
}
