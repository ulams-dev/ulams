<?php

namespace EscolaLms\Notifications\Http\Requests;

use EscolaLms\Notifications\Models\DatabaseNotification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationReadRequest extends FormRequest
{
    public function authorize()
    {
        return !is_null($this->user())
            && $this->user()->getKey() === $this->getNotification()->notifiable_id;
    }

    protected function prepareForValidation()
    {
        $this->merge(['notification' => $this->route('notification')]);
    }

    public function rules()
    {
        return [
            'notification' => ['required', 'string', Rule::exists('notifications', 'id')],
        ];
    }

    public function getNotification(): DatabaseNotification
    {
        return DatabaseNotification::find($this->route('notification'));
    }
}
