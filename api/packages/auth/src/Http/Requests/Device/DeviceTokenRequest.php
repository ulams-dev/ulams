<?php

namespace Ulams\Auth\Http\Requests\Device;

use Illuminate\Foundation\Http\FormRequest;

class DeviceTokenRequest extends FormRequest
{
    public const GRANT_TYPE = 'urn:ietf:params:oauth:grant-type:device_code';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'device_code' => ['required', 'string', 'max:128'],
            'grant_type' => ['nullable', 'in:' . self::GRANT_TYPE],
        ];
    }
}
