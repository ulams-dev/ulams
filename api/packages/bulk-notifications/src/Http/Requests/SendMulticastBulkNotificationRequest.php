<?php

namespace EscolaLms\BulkNotifications\Http\Requests;

use EscolaLms\BulkNotifications\Channels\PushNotificationChannel;
use EscolaLms\BulkNotifications\Dtos\SendUserBulkNotificationDto;
use EscolaLms\BulkNotifications\Dtos\SendMulticastBulkNotificationDto;
use EscolaLms\BulkNotifications\Models\BulkNotification;
use EscolaLms\BulkNotifications\Rules\RequiredSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *      schema="SendMulticastBulkNotificationRequest",
 *      required={"channel", "sections"},
 *      @OA\Property(
 *          property="channel",
 *          description="channel",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="sections",
 *          description="sections",
 *          type="array",
 *          @OA\Items(
 *              @OA\Property(property="title", type="string"),
 *              @OA\Property(property="body", type="string"),
 *              @OA\Property(property="image_url", type="string"),
 *              @OA\Property(property="redirect_url", type="string"),
 *              @OA\Property(property="data", type="object")
 *          )
 *      ),
 * )
 *
 */
class SendMulticastBulkNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', BulkNotification::class);
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in(PushNotificationChannel::class)],
            'sections' => ['required', new RequiredSection()],
            'sections.*' => ['required'],
        ];
    }

    public function toDto(): SendMulticastBulkNotificationDto
    {
        return SendMulticastBulkNotificationDto::instantiateFromRequest($this);
    }
}
