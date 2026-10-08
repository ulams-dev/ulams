<?php

namespace Ulams\Scorm\Http\Requests;

use Ulams\Core\Models\User;
use Ulams\Scorm\Enums\ScormPermissionsEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ScormDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->route('scormModel'));
    }

    public function rules(): array
    {
        return [];
    }
}
