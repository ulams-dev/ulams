<?php

namespace EscolaLms\Files\Http\Requests;

use EscolaLms\Files\Enums\FilePermissionsEnum;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Http\FormRequest;

class FileMoveRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->user();
        return $user!=null && $user->can(FilePermissionsEnum::FILE_UPDATE, 'api');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'source_url' => ['string','required'],
            'destination_url' => ['string','required'],
        ];
    }

    public function getAcceptableContentTypes(): array
    {
        return ['application/json'];
    }

    public function getParamSource(): string
    {
        return $this->get('source_url');
    }

    public function getParamDestination(): string
    {
        return $this->get('destination_url');
    }
}
