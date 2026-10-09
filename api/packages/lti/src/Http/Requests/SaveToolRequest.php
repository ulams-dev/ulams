<?php

namespace Ulams\Lti\Http\Requests;

class SaveToolRequest extends LtiManageRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        // a key source is required on create; updates may change one field at a time
        $keySource = fn (string $other) => $this->isMethod('post') ? 'required_without:' . $other : 'nullable';

        return [
            'name' => [$required, 'string', 'max:255'],
            'oidc_login_url' => [$required, 'url', 'max:2048'],
            'launch_url' => [$required, 'url', 'max:2048'],
            'deep_linking_url' => ['nullable', 'url', 'max:2048'],
            'redirect_uris' => ['nullable', 'array', 'max:20'],
            'redirect_uris.*' => ['url', 'max:2048'],
            'jwks_url' => ['nullable', $keySource('public_key'), 'url', 'max:2048'],
            'public_key' => ['nullable', $keySource('jwks_url'), 'string', 'max:10000', 'starts_with:-----BEGIN PUBLIC KEY-----'],
            'custom' => ['nullable', 'array'],
            'custom.*' => ['string', 'max:1024'],
            'share_name' => ['boolean'],
            'share_email' => ['boolean'],
            'nrps_enabled' => ['boolean'],
            'enabled' => ['boolean'],
        ];
    }
}
