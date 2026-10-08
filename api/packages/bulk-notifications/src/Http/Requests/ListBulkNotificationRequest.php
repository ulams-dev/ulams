<?php

namespace Ulams\BulkNotifications\Http\Requests;

use Ulams\BulkNotifications\Dtos\OrderDto;
use Ulams\BulkNotifications\Dtos\PageDto;
use Ulams\BulkNotifications\Models\BulkNotification;
use Ulams\BulkNotifications\Dtos\CriteriaBulkNotificationDto;
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
