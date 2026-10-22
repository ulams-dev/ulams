<?php

namespace Ulams\Identity\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Ulams\Auth\Exceptions\InvalidReturnUrlException;
use Ulams\Auth\Exceptions\LoginHandoffException;
use Ulams\Auth\Http\Resources\LoginResource;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Identity\Exceptions\OidcException;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\SsoLoginService;

/**
 * Sign-in through a tenant's identity provider (OpenID Connect). Public routes on the tenant API host: the
 * provider rows live in the tenant's own database, so another tenant's slug, state or code never resolves here.
 *
 * @OA\Get(path="/api/identity/providers", operationId="identity.providers.public", summary="Identity providers a learner can sign in with", tags={"Identity"},
 *     @OA\Response(response=200, description="Enabled providers: slug, name and protocol, nothing else", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", type="array", @OA\Items(type="object",
 *             @OA\Property(property="slug", type="string", example="acme-keycloak"),
 *             @OA\Property(property="name", type="string", example="Acme staff"),
 *             @OA\Property(property="protocol", type="string", enum={"oidc"}))))))
 * @OA\Get(path="/api/identity/sso/{slug}/start", operationId="identity.sso.start", summary="Start a sign-in: redirects the browser to the provider", tags={"Identity"},
 *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="challenge", in="query", required=true, description="base64url SHA-256 of a verifier only the caller's server knows (43 characters)", @OA\Schema(type="string")),
 *     @OA\Parameter(name="return_to", in="query", description="Where to send the browser afterwards; must be an origin of this academy (default: the learner app)", @OA\Schema(type="string", format="uri")),
 *     @OA\Response(response=302, description="Redirect to the provider's authorization endpoint"),
 *     @OA\Response(response=404, description="Unknown or disabled provider"),
 *     @OA\Response(response=422, description="Return URL not allowed or challenge missing"),
 *     @OA\Response(response=502, description="The provider could not be reached or is misconfigured"))
 * @OA\Get(path="/api/identity/oidc/{slug}/callback", operationId="identity.oidc.callback", summary="Where the provider sends the browser back; redirects to the return URL with a one-time code or an error", tags={"Identity"},
 *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="state", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="code", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="error", in="query", @OA\Schema(type="string")),
 *     @OA\Response(response=302, description="Redirect to the return URL with ?code=... (success) or ?error=provider_denied|provider_failed|link_required|email_required|email_unverified|account_unavailable|signup_disabled"),
 *     @OA\Response(response=400, description="The state is invalid, expired or already used"))
 * @OA\Post(path="/api/identity/sso/exchange", operationId="identity.sso.exchange", summary="Redeem the one-time code with the verifier for an access token", tags={"Identity"},
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"code","verifier"},
 *         @OA\Property(property="code", type="string", description="The one-time code from the return URL (43 characters)"),
 *         @OA\Property(property="verifier", type="string", description="The secret whose SHA-256 was sent as the challenge (43 to 128 characters)"))),
 *     @OA\Response(response=200, description="Access token", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", type="object", @OA\Property(property="token", type="string"), @OA\Property(property="expires_at", type="string", format="date-time")))),
 *     @OA\Response(response=400, description="The code is invalid, expired, used already or does not match the verifier"))
 */
class SsoController extends UlamsBaseController
{
    public function __construct(private readonly SsoLoginService $sso)
    {
    }

    public function providers(): JsonResponse
    {
        $rows = IdentityProvider::query()->where('enabled', true)->where('protocol', IdentityProvider::OIDC)->orderBy('name')->get(['slug', 'name', 'protocol']);

        return $this->sendResponse($rows->map(fn (IdentityProvider $p) => ['slug' => $p->slug, 'name' => $p->name, 'protocol' => $p->protocol])->all());
    }

    public function start(Request $request, string $slug): RedirectResponse|JsonResponse
    {
        $provider = $this->sso->provider($slug);
        if ($provider === null) {
            return $this->sendError(__('Unknown sign-in provider.'), 404);
        }

        try {
            $url = $this->sso->start(
                $provider,
                $request->getHost(),
                $request->getSchemeAndHttpHost() . '/api/identity/oidc/' . $provider->slug . '/callback',
                $request->query('return_to') !== null ? (string) $request->query('return_to') : null,
                $request->query('challenge') !== null ? (string) $request->query('challenge') : null
            );
        } catch (InvalidReturnUrlException $e) {
            return $this->sendError($e->getMessage(), 422);
        } catch (\InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), 422);
        } catch (OidcException $e) {
            report($e);

            return $this->sendError(__('The sign-in provider is not available. Try again later.'), 502);
        }

        return redirect()->away($url)->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function callback(Request $request, string $slug): RedirectResponse|JsonResponse
    {
        try {
            $url = $this->sso->handleCallback(
                $slug,
                $this->param($request, 'state'),
                $this->param($request, 'code'),
                $request->query->has('error') ? (string) $request->query('error') : null,
                $request->getHost()
            );
        } catch (LoginHandoffException) {
            // without a valid state there is no trusted place to send the browser to
            return $this->sendError(__('The login attempt is invalid or has expired. Start again.'), 400);
        }

        return redirect()->away($url)->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function exchange(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:128'], 'verifier' => ['required', 'string', 'max:256']]);

        try {
            $result = $this->sso->exchange($data['code'], $data['verifier'], $request->getHost());
        } catch (LoginHandoffException) {
            return $this->sendError(__('The login code is invalid or has expired. Start again.'), 400);
        }

        return $this->sendResponse(LoginResource::make($result['token'])->toArray($request), __('Login successful'))->withHeaders(['Cache-Control' => 'no-store']);
    }

    private function param(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : null;
    }
}
