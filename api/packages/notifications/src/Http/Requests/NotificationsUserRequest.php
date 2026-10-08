<?php

namespace EscolaLms\Notifications\Http\Requests;

use EscolaLms\Notifications\Enums\NotificationsPermissionsEnum;
use EscolaLms\Notifications\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationsUserRequest extends FormRequest
{
    public function authorize()
    {
        return !is_null($this->user());
    }

    protected function prepareForValidation()
    {
        if ($this->user() && $this->user()->can(NotificationsPermissionsEnum::READ_ALL_NOTIFICATIONS) && $this->route('user')) {
            $this->merge(['user' => $this->route('user')]);
        } else if ($this->user()) {
            $this->merge(['user' => $this->user()->getKey()]);
        }
    }

    public function rules()
    {
        return [
            'user' => ['required', 'integer', Rule::exists('users', 'id')],
            'event' => ['sometimes', 'nullable', 'string'],
            'include_read' => ['sometimes', 'boolean'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'order_by' => ['sometimes', 'string', 'in:created_at,event'],
            'order' => ['sometimes', 'string', 'in:ASC,DESC'],
        ];
    }

    public function getUserFromRoute(): User
    {
        return User::find($this->input('user'));
    }

    public function getEvent(): ?string
    {
        return $this->input('event');
    }

    public function getIncludeRead(): bool
    {
        return $this->input('include_read', ($this->user()->getKey() !== $this->getUserFromRoute()->getKey()));
    }

    public function getDateFrom(): ?string
    {
        return $this->input('date_from');
    }

    public function getDateTo(): ?string
    {
        return $this->input('date_to');
    }
}
