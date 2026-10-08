<?php

namespace Ulams\Cmi5\Http\Requests;

use Ulams\Cmi5\Models\Cmi5;
use Ulams\Cmi5\Rules\Cmi5Rule;
use Ulams\Uploads\Rules\SafeUpload;
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
            'file' => ['bail', 'required', 'file', 'mimes:zip', new Cmi5Rule(), new SafeUpload('cmi5')]
        ];
    }
}
