<?php

namespace EscolaLms\Files\Http\Requests;

use EscolaLms\Files\Enums\FilePermissionsEnum;
use EscolaLms\Files\Helpers\FileHelper;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Http\FormRequest;

class FileUploadRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->user();
        return $user!=null && $user->can(FilePermissionsEnum::FILE_CREATE, 'api');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'target' => 'required',
            'file' => ['required', 'array'],
            'file.*' => ['required', FileHelper::getMimesRule()],
        ];
    }
}
