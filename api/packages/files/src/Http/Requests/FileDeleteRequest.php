<?php

namespace EscolaLms\Files\Http\Requests;

use EscolaLms\Files\Enums\FilePermissionsEnum;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Http\FormRequest;

class FileDeleteRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->user();
        return $user!=null && $user->can(FilePermissionsEnum::FILE_DELETE, 'api');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'url' => ['required','string'],
        ];
    }

    public function getAcceptableContentTypes(): array
    {
        return ['multipart/form-data'];
    }

    public function getParamUrl(): string
    {
        return $this->get('url');
    }
}
