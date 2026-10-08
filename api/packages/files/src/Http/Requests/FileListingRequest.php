<?php

namespace EscolaLms\Files\Http\Requests;

use EscolaLms\Files\Enums\FilePermissionsEnum;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Http\FormRequest;

class FileListingRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->user();
        return $user != null && ($user->can(FilePermissionsEnum::FILE_LIST, 'api') || $user->can(FilePermissionsEnum::FILE_LIST_SELF, 'api'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'directory' => 'required',
            'page' => 'nullable|integer|min:1',
            'perPage' => 'nullable|integer|min:0',
        ];
    }

    public function getDirectory(): string
    {
        return $this->get('directory');
    }

    public function getPage(): int
    {
        return $this->get('page', 1);
    }

    public function getPerPage(): int
    {
        return $this->get('perPage', 50);
    }

    public function getAcceptableContentTypes(): array
    {
        return ['application/json'];
    }
}
