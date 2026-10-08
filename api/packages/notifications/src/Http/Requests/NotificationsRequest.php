<?php

namespace EscolaLms\Notifications\Http\Requests;

use EscolaLms\Notifications\Enums\NotificationsPermissionsEnum;
use Illuminate\Foundation\Http\FormRequest;

class NotificationsRequest extends FormRequest
{
    public function authorize()
    {
        return !is_null($this->user()) && $this->user()->can(NotificationsPermissionsEnum::READ_ALL_NOTIFICATIONS);
    }

    public function rules()
    {
        return [
            'event' => ['sometimes', 'nullable', 'string'],
            'include_read' => ['sometimes', 'boolean'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'order_by' => ['sometimes', 'string', 'in:created_at,notifiable_id,event'],
            'order' => ['sometimes', 'string', 'in:ASC,DESC'],
        ];
    }

    public function getEvent(): ?string
    {
        return $this->input('event');
    }

    public function getIncludeRead(): bool
    {
        return $this->input('include_read', false);
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
