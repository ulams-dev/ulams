<?php

namespace EscolaLms\BulkNotifications\Http\Requests;

use EscolaLms\BulkNotifications\Dtos\CreateDeviceTokenDto;
use EscolaLms\BulkNotifications\Models\DeviceToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="CreateDeviceTokenRequest",
 *      required={"token"},
 *      @OA\Property(
 *          property="token",
 *          description="token",
 *          type="string"
 *      )
 * )
 *
 */
class CreateDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', DeviceToken::class);
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
        ];
    }

    public function toDto(): CreateDeviceTokenDto
    {
        return CreateDeviceTokenDto::instantiateFromRequest($this);
    }
}
