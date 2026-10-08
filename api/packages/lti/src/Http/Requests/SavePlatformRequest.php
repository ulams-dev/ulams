<?php

namespace Ulams\Lti\Http\Requests;

class SavePlatformRequest extends LtiManageRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'issuer' => [$required, 'string', 'max:255'],
            'client_id' => [$required, 'string', 'max:255'],
            'deployment_ids' => [$required, 'array', 'min:1', 'max:50'],
            'deployment_ids.*' => ['string', 'max:255'],
            'auth_login_url' => [$required, 'url', 'max:2048'],
            'auth_token_url' => [$required, 'url', 'max:2048'],
            'auth_server' => ['nullable', 'string', 'max:2048'],
            'jwks_url' => [$required, 'url', 'max:2048'],
            'default_course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'enabled' => ['boolean'],
        ];
    }
}
