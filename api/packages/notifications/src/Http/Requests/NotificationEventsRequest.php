<?php

namespace EscolaLms\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotificationEventsRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user();
    }

    public function rules()
    {
        return [];
    }
}
