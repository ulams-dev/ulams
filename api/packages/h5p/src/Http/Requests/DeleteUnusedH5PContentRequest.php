<?php

namespace Ulams\H5P\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Ulams\H5P\Models\H5PContent;

class DeleteUnusedH5PContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('deleteUnused', H5PContent::class);
    }

    public function rules(): array
    {
        return [];
    }
}
