<?php

namespace Ulams\Interactive\Http\Requests;

use Ulams\Uploads\Rules\SafeUpload;

class InteractiveVersionCreateRequest extends InteractiveRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', new SafeUpload('interactive')],
            'change_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
