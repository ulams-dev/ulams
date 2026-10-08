<?php

namespace EscolaLms\PencilSpaces\Http\Requests;

use EscolaLms\PencilSpaces\Enums\PencilSpacesPermissionEnum;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *      schema="LoginPencilSpaceRequest",
 *      required={"redirect_url"},
 *      @OA\Property(
 *          property="redirect_url",
 *          type="string",
 *          example="https://my.pencilapp.com"
 *      )
 * )
 *
 */
class LoginPencilSpaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(PencilSpacesPermissionEnum::PENCIL_SPACES_LOGIN);
    }

    public function rules(): array
    {
        return [
            'redirect_url' => ['required', 'url'],
        ];
    }

    public function getUserId(): int
    {
        return $this->user()->getKey();
    }

    public function getUrl(): string
    {
        return $this->get('redirect_url');
    }
}
