<?php

namespace Ulams\Lti\Tool;

use Packback\Lti1p3\Interfaces\IDatabase;
use Packback\Lti1p3\Interfaces\ILtiDeployment;
use Packback\Lti1p3\Interfaces\ILtiRegistration;
use Packback\Lti1p3\LtiDeployment;
use Packback\Lti1p3\LtiRegistration;
use Ulams\Lti\Models\LtiPlatform;
use Ulams\Lti\Services\KeyService;

/**
 * packbackbooks/lti-1p3-tool registrations backed by `lti_platforms` and our tenant key set.
 */
class ToolDatabase implements IDatabase
{
    public function __construct(private readonly KeyService $keys)
    {
    }

    public function findRegistrationByIssuer(string $iss, ?string $clientId = null): ?ILtiRegistration
    {
        $platform = $this->platform($iss, $clientId);

        return $platform === null ? null : $this->registration($platform);
    }

    public function findDeployment(string $iss, string $deploymentId, ?string $clientId = null): ?ILtiDeployment
    {
        $platform = $this->platform($iss, $clientId);
        if ($platform === null || !in_array($deploymentId, $platform->deployment_ids ?? [], true)) {
            return null;
        }

        return LtiDeployment::new($deploymentId);
    }

    public function platform(string $iss, ?string $clientId): ?LtiPlatform
    {
        $query = LtiPlatform::query()->where('issuer', $iss)->where('enabled', true);
        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        } elseif (LtiPlatform::query()->where('issuer', $iss)->where('enabled', true)->count() > 1) {
            return null; // ambiguous without client_id
        }

        return $query->first();
    }

    public function registration(LtiPlatform $platform): LtiRegistration
    {
        $key = $this->keys->activeKey();

        return LtiRegistration::new([
            'issuer' => $platform->issuer,
            'clientId' => $platform->client_id,
            'keySetUrl' => $platform->jwks_url,
            'authTokenUrl' => $platform->auth_token_url,
            'authLoginUrl' => $platform->auth_login_url,
            'authServer' => $platform->auth_server ?: $platform->auth_token_url,
            'toolPrivateKey' => $key->private_key,
            'kid' => $key->kid,
        ]);
    }
}
