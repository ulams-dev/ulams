<?php

namespace Ulams\Tenancy\Http\Requests;

class DeletePlatformTenantRequest extends PlatformRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['confirm' => ['required', 'string', 'in:' . $this->route('slug')]];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['confirm.in' => 'Send the tenant slug in `confirm` to delete it. This removes its database, files and settings for good.'];
    }
}
