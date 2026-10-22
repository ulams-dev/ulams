<?php

namespace Ulams\Identity\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ulams\Auth\Exceptions\InvalidReturnUrlException;
use Ulams\Auth\Exceptions\LoginHandoffException;
use Ulams\Auth\Support\ReturnUrlPolicy;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Identity\Exceptions\SamlException;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\IdentityAuditLog;
use Ulams\Identity\Services\Saml\SamlContext;
use Ulams\Identity\Services\Saml\SamlLogout;
use Ulams\Identity\Services\Saml\SamlService;
use Ulams\Identity\Services\SsoLoginService;

/**
 * SAML 2.0 service provider endpoints of a tenant's identity provider (plan 4.2): the SP metadata an administrator
 * gives the IdP, the assertion consumer service the browser posts the IdP's response to, single logout, and the
 * call that starts a logout from this side. Public routes on the tenant API host (the provider rows live in the
 * tenant's own database), except the logout call, which needs the user's token.
 *
 * @OA\Get(path="/api/identity/saml/{slug}/metadata", operationId="identity.saml.metadata", summary="SAML service provider metadata of an identity provider", tags={"Identity"},
 *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="SAML 2.0 metadata XML: entity id (this URL), assertion consumer service, single logout service and the certificates to import at the identity provider", @OA\MediaType(mediaType="application/samlmetadata+xml", @OA\Schema(type="string"))),
 *     @OA\Response(response=404, description="Unknown provider or not a SAML provider"))
 * @OA\Post(path="/api/identity/saml/{slug}/acs", operationId="identity.saml.acs", summary="Assertion consumer service: the identity provider's browser POST (SAMLResponse and RelayState)", tags={"Identity"},
 *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/x-www-form-urlencoded", @OA\Schema(required={"SAMLResponse","RelayState"},
 *         @OA\Property(property="SAMLResponse", type="string", description="Base64 SAML response, at most 256 KB"),
 *         @OA\Property(property="RelayState", type="string", description="The signed login state this service gave the identity provider in the AuthnRequest redirect")))),
 *     @OA\Response(response=302, description="Redirect to the return URL with ?code=... (success) or ?error=provider_failed|link_required|email_required|email_unverified|account_unavailable|signup_disabled"),
 *     @OA\Response(response=400, description="The state is invalid, expired or already used (nothing trusted says where to send the browser)"),
 *     @OA\Response(response=413, description="The body is too large"))
 * @OA\Get(path="/api/identity/saml/{slug}/slo", operationId="identity.saml.slo", summary="Single logout service (redirect binding): a LogoutRequest from the identity provider or the LogoutResponse to ours", tags={"Identity"},
 *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="SAMLRequest", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="SAMLResponse", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="RelayState", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="SigAlg", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="Signature", in="query", @OA\Schema(type="string")),
 *     @OA\Response(response=302, description="Redirect to the identity provider with our LogoutResponse, or to the return URL after a logout started here"),
 *     @OA\Response(response=200, description="Logged out; the identity provider has no logout response endpoint"),
 *     @OA\Response(response=400, description="The message is not valid: unsigned, wrong issuer or destination, expired, replayed or unknown"),
 *     @OA\Response(response=404, description="Unknown provider or not a SAML provider"))
 * @OA\Post(path="/api/identity/saml/{slug}/slo", operationId="identity.saml.slo.post", summary="Single logout service (POST binding with an enveloped XML signature)", tags={"Identity"},
 *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/x-www-form-urlencoded", @OA\Schema(
 *         @OA\Property(property="SAMLRequest", type="string"), @OA\Property(property="SAMLResponse", type="string"), @OA\Property(property="RelayState", type="string")))),
 *     @OA\Response(response=302, description="Redirect to the identity provider with our LogoutResponse, or to the return URL"),
 *     @OA\Response(response=200, description="Logged out"),
 *     @OA\Response(response=400, description="The message is not valid"),
 *     @OA\Response(response=404, description="Unknown provider or not a SAML provider"))
 * @OA\Post(path="/api/identity/saml/{slug}/logout", operationId="identity.saml.logout", summary="Sign out of this academy and, when the identity provider supports it, start single logout there", tags={"Identity"}, security={{"passport": {}}},
 *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="return_to", type="string", format="uri", description="Where the browser lands after the identity provider logged it out; must be an origin of this academy"))),
 *     @OA\Response(response=200, description="The token is revoked. redirect_url is the signed LogoutRequest URL to send the browser to, or null when this login did not come from the provider or it has no single logout", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", type="object", @OA\Property(property="redirect_url", type="string", format="uri", nullable=true)))),
 *     @OA\Response(response=404, description="Unknown provider or not a SAML provider"),
 *     @OA\Response(response=422, description="Return URL not allowed"))
 */
class SamlController extends UlamsBaseController
{
    public function __construct(
        private readonly SsoLoginService $sso,
        private readonly SamlService $saml,
        private readonly SamlLogout $logout,
        private readonly IdentityAuditLog $audit,
        private readonly ReturnUrlPolicy $returnUrls
    ) {
    }

    public function metadata(Request $request, string $slug): Response|JsonResponse
    {
        $provider = $this->provider($slug, false);
        if ($provider === null) {
            return $this->sendError(__('Unknown sign-in provider.'), 404);
        }
        try {
            $xml = $this->saml->metadataXml($provider, SamlContext::fromRequest($request));
        } catch (SamlException $e) {
            report($e);

            return $this->sendError(__('The sign-in provider is not available. Try again later.'), 404);
        }

        return response($xml, 200, ['Content-Type' => 'application/samlmetadata+xml; charset=UTF-8', 'Cache-Control' => 'no-cache']);
    }

    public function acs(Request $request, string $slug): RedirectResponse|JsonResponse
    {
        if ($this->tooLarge($request)) {
            return $this->sendError(__('The request is too large.'), 413);
        }
        $relay = $request->request->get('RelayState');
        $response = $request->request->get('SAMLResponse');

        try {
            $url = $this->sso->handleAcs($slug, SamlContext::fromRequest($request), is_string($relay) ? $relay : null, is_string($response) ? $response : null);
        } catch (LoginHandoffException) {
            // without a valid state there is no trusted place to send the browser to
            return $this->sendError(__('The login attempt is invalid or has expired. Start again.'), 400);
        }

        return redirect()->away($url)->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function slo(Request $request, string $slug): RedirectResponse|JsonResponse
    {
        $provider = $this->provider($slug, true);
        if ($provider === null) {
            return $this->sendError(__('Unknown sign-in provider.'), 404);
        }
        if ($this->tooLarge($request)) {
            return $this->sendError(__('The request is too large.'), 413);
        }

        try {
            $result = $this->logout->handle($provider, SamlContext::fromRequest($request), $request);
        } catch (SamlException $e) {
            // garbage anybody can send is not worth a row; a message that parsed and failed a check is
            if (!in_array($e->reason, [SamlException::BAD_ENCODING, SamlException::TOO_LARGE, SamlException::DOCTYPE, SamlException::INVALID_XML, SamlException::INVALID], true)) {
                $this->audit->record('saml.logout_failed', ['actor' => 'idp', 'actor_id' => null, 'provider_id' => $provider->getKey(), 'data' => ['reason' => $e->reason]]);
            }

            return $this->sendError(__('The logout message is not valid.'), 400);
        }
        $this->audit->record('saml.logout', ['actor' => 'idp', 'actor_id' => null, 'provider_id' => $provider->getKey(), 'data' => ['kind' => $result['kind'], 'revoked' => $result['revoked']]]);

        if ($result['redirect'] === null) {
            return $this->sendSuccess(__('Logged out.'));
        }

        return redirect()->away($result['redirect'])->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function logout(Request $request, string $slug): JsonResponse
    {
        $provider = $this->provider($slug, true);
        if ($provider === null) {
            return $this->sendError(__('Unknown sign-in provider.'), 404);
        }
        $returnTo = $request->input('return_to');
        $returnTo = is_string($returnTo) && $returnTo !== '' ? $returnTo : $this->returnUrls->default();
        if (!$this->returnUrls->isAllowed($returnTo)) {
            return $this->sendError((new InvalidReturnUrlException())->getMessage(), 422);
        }

        $token = $request->user()?->token();
        $url = null;
        if ($token !== null) {
            try {
                $url = $this->logout->initiate($provider, SamlContext::fromRequest($request), (string) $token->getKey(), $returnTo);
            } catch (SamlException $e) {
                report($e);
            }
            $token->revoke();
        }

        return $this->sendResponse(['redirect_url' => $url], __('Logged out.'))->withHeaders(['Cache-Control' => 'no-store']);
    }

    private function tooLarge(Request $request): bool
    {
        return (int) $request->headers->get('Content-Length', 0) > 2 * (int) config('ulams_identity.saml.max_body_bytes');
    }

    private function provider(string $slug, bool $enabledOnly): ?IdentityProvider
    {
        $query = IdentityProvider::query()->where('slug', $slug)->where('protocol', IdentityProvider::SAML);
        if ($enabledOnly) {
            $query->where('enabled', true);
        }

        return $query->first();
    }
}
