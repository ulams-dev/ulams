<?php

namespace EscolaLms\BulkNotifications\Http\Requests;

use EscolaLms\BulkNotifications\Dtos\OrderDto;
use EscolaLms\BulkNotifications\Dtos\PageDto;
use EscolaLms\BulkNotifications\Models\BulkNotification;
use EscolaLms\BulkNotifications\Dtos\CriteriaBulkNotificationDto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListBulkNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', BulkNotification::class);
    }

    public function rules(): array
    {
        return [];
    }

    public function getCriteria(): CriteriaBulkNotificationDto
    {
        return CriteriaBulkNotificationDto::instantiateFromRequest($this);
    }

    public function getPage(): PageDto
    {
        return PageDto::instantiateFromRequest($this);
    }

    public function getOrder(): OrderDto
    {
        return OrderDto::instantiateFromRequest($this);
    }
}
