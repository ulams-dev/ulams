<?php

namespace EscolaLms\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotificationReadAllRequest extends FormRequest
{
    public function authorize(): bool
    {
        return !is_null($this->user());
    }

    public function rules(): array
    {
        return [];
    }
}
