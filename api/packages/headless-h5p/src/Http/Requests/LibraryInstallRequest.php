<?php

namespace Ulams\HeadlessH5P\Http\Requests;

use Ulams\HeadlessH5P\Models\H5PLibrary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class LibraryInstallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('install', H5PLibrary::class);
    }

    public function getMachineName(): string
    {
        return $this->get('id');
    }

    public function rules(): array
    {
        return [];
    }
}
