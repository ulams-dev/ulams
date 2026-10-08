<?php

namespace EscolaLms\Cmi5\Http\Requests;

use EscolaLms\Cmi5\Models\Cmi5;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class Cmi5ListRequest extends FormRequest
{
    public function authorize(): bool
    {
         return Gate::allows('list', Cmi5::class);
    }

    public function rules(): array
    {
        return [];
    }
}
