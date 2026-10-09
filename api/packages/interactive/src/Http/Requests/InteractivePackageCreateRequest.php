<?php

namespace Ulams\Interactive\Http\Requests;

use Ulams\Uploads\Rules\SafeUpload;

class InteractivePackageCreateRequest extends InteractiveRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', new SafeUpload('interactive')],
            'title' => ['nullable', 'string', 'max:255'],
            'change_note' => ['nullable', 'string', 'max:500'],
            'accept_network' => ['nullable', 'boolean'],
        ];
    }
}
