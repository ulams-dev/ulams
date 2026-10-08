<?php

namespace EscolaLms\Cmi5\Http\Requests;

use EscolaLms\Cmi5\Models\Cmi5;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class Cmi5DeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
         return Gate::allows('delete', $this->getCmi5());
    }

    public function rules(): array
    {
        return [];
    }

    public function getCmi5(): Cmi5
    {
        return Cmi5::findOrFail($this->route('id'));
    }
}
