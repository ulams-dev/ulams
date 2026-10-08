<?php

namespace Ulams\Scorm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Peopleaps\Scorm\Model\ScormModel;
use Ulams\Uploads\Rules\SafeUpload;

class ScormCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', ScormModel::class);
    }

    public function rules(): array
    {
        return [
            'zip' => ['file', 'required', 'mimes:zip', new SafeUpload('scorm')],
        ];
    }
}
