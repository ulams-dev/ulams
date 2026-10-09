<?php

namespace Ulams\Interactive\Http\Requests;

class InteractivePackageUpdateRequest extends InteractiveRequest
{
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:255']];
    }
}
