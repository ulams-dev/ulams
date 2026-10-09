<?php

namespace Ulams\ExamplePlugin\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Ulams\Core\Models\User;
use Ulams\ExamplePlugin\Enums\ExamplePluginPermissionEnum;

/**
 * @OA\Schema(
 *      schema="ExamplePluginSendGreetingRequest",
 *      required={"user_id"},
 *      @OA\Property(property="user_id", description="Recipient", type="integer")
 * )
 */
class SendGreetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(ExamplePluginPermissionEnum::SEND_GREETING);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function getRecipient(): User
    {
        return User::query()->findOrFail($this->validated('user_id'));
    }
}
