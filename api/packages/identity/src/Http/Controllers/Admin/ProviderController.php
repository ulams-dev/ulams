<?php

namespace Ulams\Identity\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Identity\Enums\IdentityPermissionEnum;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\ProviderService;

/**
 * Identity providers of the tenant (admin only, permission `identity_provider_manage`). The client secret is
 * write-only: responses say whether one is set and, for long secrets, its last four characters.
 *
 * @OA\Schema(schema="IdentityProvider", type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="slug", type="string", description="Used in the sign-in URLs; fixed after creation"),
 *     @OA\Property(property="name", type="string", description="Shown on the sign-in button"),
 *     @OA\Property(property="protocol", type="string", enum={"oidc"}),
 *     @OA\Property(property="enabled", type="boolean"),
 *     @OA\Property(property="email_domains", type="array", @OA\Items(type="string"), description="Domains of the organisation"),
 *     @OA\Property(property="trust_email", type="boolean", description="The provider is authoritative for e-mail addresses of email_domains: an existing non-privileged account with a verified address there is linked on first sign-in"),
 *     @OA\Property(property="jit_enabled", type="boolean", description="Create an account on first sign-in"),
 *     @OA\Property(property="default_role", type="string", enum={"student","tutor"}),
 *     @OA\Property(property="oidc", type="object",
 *         @OA\Property(property="issuer", type="string", format="uri"),
 *         @OA\Property(property="client_id", type="string"),
 *         @OA\Property(property="scopes", type="array", @OA\Items(type="string")),
 *         @OA\Property(property="token_auth", type="string", enum={"client_secret_basic","client_secret_post"}),
 *         @OA\Property(property="userinfo", type="boolean")),
 *     @OA\Property(property="client_secret_set", type="boolean"),
 *     @OA\Property(property="client_secret_hint", type="string", nullable=true),
 *     @OA\Property(property="redirect_uri", type="string", format="uri", description="Register this redirect URI at the provider"),
 *     @OA\Property(property="last_login_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="last_error", type="string", nullable=true, description="Reason code of the last failed sign-in"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"))
 * @OA\Schema(schema="IdentityProviderInput", type="object",
 *     @OA\Property(property="slug", type="string", description="Lowercase letters, digits and hyphens; required on create, ignored on update"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="enabled", type="boolean"),
 *     @OA\Property(property="email_domains", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="trust_email", type="boolean"),
 *     @OA\Property(property="jit_enabled", type="boolean"),
 *     @OA\Property(property="default_role", type="string", enum={"student","tutor"}),
 *     @OA\Property(property="oidc", type="object",
 *         @OA\Property(property="issuer", type="string", format="uri"),
 *         @OA\Property(property="client_id", type="string"),
 *         @OA\Property(property="scopes", type="array", @OA\Items(type="string")),
 *         @OA\Property(property="token_auth", type="string", enum={"client_secret_basic","client_secret_post"}),
 *         @OA\Property(property="userinfo", type="boolean")),
 *     @OA\Property(property="client_secret", type="string", description="Write-only; stored encrypted"))
 *
 * @OA\Get(path="/api/admin/identity/providers", operationId="identity.providers.list", summary="Identity providers of this academy", tags={"Admin Identity"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="Providers", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/IdentityProvider")))),
 *     @OA\Response(response=403, description="Needs identity_provider_manage"))
 * @OA\Post(path="/api/admin/identity/providers", operationId="identity.providers.create", summary="Add an OpenID Connect identity provider", tags={"Admin Identity"}, security={{"passport": {}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/IdentityProviderInput")),
 *     @OA\Response(response=201, description="Created", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/IdentityProvider"))),
 *     @OA\Response(response=403, description="Needs identity_provider_manage"),
 *     @OA\Response(response=422, description="Invalid input, duplicate slug, or the academy has too many providers"))
 * @OA\Get(path="/api/admin/identity/providers/{provider}", operationId="identity.providers.get", summary="One identity provider", tags={"Admin Identity"}, security={{"passport": {}}},
 *     @OA\Parameter(name="provider", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Provider", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/IdentityProvider"))),
 *     @OA\Response(response=404, description="Not found"))
 * @OA\Put(path="/api/admin/identity/providers/{provider}", operationId="identity.providers.update", summary="Change an identity provider (leave client_secret out to keep the stored one)", tags={"Admin Identity"}, security={{"passport": {}}},
 *     @OA\Parameter(name="provider", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/IdentityProviderInput")),
 *     @OA\Response(response=200, description="Updated", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/IdentityProvider"))),
 *     @OA\Response(response=404, description="Not found"),
 *     @OA\Response(response=422, description="Invalid input"))
 * @OA\Delete(path="/api/admin/identity/providers/{provider}", operationId="identity.providers.delete", summary="Delete an identity provider and its account links (accounts stay)", tags={"Admin Identity"}, security={{"passport": {}}},
 *     @OA\Parameter(name="provider", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"))),
 *     @OA\Response(response=404, description="Not found"))
 */
class ProviderController extends UlamsBaseController
{
    public function __construct(private readonly ProviderService $providers)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        return $this->sendResponse(IdentityProvider::query()->orderBy('name')->get()->map(fn (IdentityProvider $p) => self::present($p, $request))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate($this->rules(true));

        $provider = $this->providers->create($data);

        return $this->sendResponse(self::present($provider->refresh(), $request), 'Identity provider created.', 201);
    }

    public function show(Request $request, string $provider): JsonResponse
    {
        $this->authorizeManage($request);

        return $this->sendResponse(self::present($this->find($provider), $request));
    }

    public function update(Request $request, string $provider): JsonResponse
    {
        $this->authorizeManage($request);
        $model = $this->find($provider);
        $data = $request->validate($this->rules(false));

        return $this->sendResponse(self::present($this->providers->update($model, $data)->refresh(), $request), 'Identity provider updated.');
    }

    public function destroy(Request $request, string $provider): JsonResponse
    {
        $this->authorizeManage($request);
        $this->providers->delete($this->find($provider));

        return $this->sendSuccess('Identity provider deleted.');
    }

    /** @return array<string,mixed> */
    private function rules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $issuerRule = function (string $attribute, mixed $value, \Closure $fail): void {
            $parts = is_string($value) ? parse_url($value) : false;
            $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
            $okScheme = $scheme === 'https' || ($scheme === 'http' && config('ulams_identity.allow_insecure_urls'));
            if (!is_array($parts) || !$okScheme || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                $fail('The issuer must be an https URL without credentials, query or fragment.');
            }
        };

        return ($creating ? ['slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/', Rule::unique('identity_providers', 'slug')]] : []) + [
            // fixed after creation: on update the key is not validated, so it never reaches the service
            'name' => [$required, 'string', 'min:1', 'max:120'],
            'enabled' => ['sometimes', 'boolean'],
            'email_domains' => ['sometimes', 'array', 'max:20'],
            'email_domains.*' => ['string', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i'],
            'trust_email' => ['sometimes', 'boolean'],
            'jit_enabled' => ['sometimes', 'boolean'],
            'default_role' => ['sometimes', Rule::in(['student', 'tutor'])],
            'oidc' => [$required, 'array'],
            'oidc.issuer' => [$required, 'string', 'max:255', $issuerRule],
            'oidc.client_id' => [$required, 'string', 'min:1', 'max:255'],
            'oidc.scopes' => ['sometimes', 'array', 'max:20'],
            'oidc.scopes.*' => ['string', 'max:100', 'regex:/^[\x21\x23-\x5B\x5D-\x7E]+$/'],
            'oidc.token_auth' => ['sometimes', Rule::in(['client_secret_basic', 'client_secret_post'])],
            'oidc.userinfo' => ['sometimes', 'boolean'],
            'client_secret' => ['sometimes', 'nullable', 'string', 'min:1', 'max:512'],
        ];
    }

    private function find(string $id): IdentityProvider
    {
        // ids of another tenant simply do not exist in this tenant's database
        abort_unless(Str::isUuid($id), 404);

        return IdentityProvider::query()->whereKey(strtolower($id))->firstOrFail();
    }

    private function authorizeManage(Request $request): void
    {
        if (!$request->user()?->can(IdentityPermissionEnum::PROVIDER_MANAGE)) {
            throw new AccessDeniedHttpException('Identity providers need the identity_provider_manage permission.');
        }
    }

    /** @return array<string,mixed> */
    public static function present(IdentityProvider $p, Request $request): array
    {
        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'name' => $p->name,
            'protocol' => $p->protocol,
            'enabled' => (bool) $p->enabled,
            'email_domains' => $p->email_domains ?? [],
            'trust_email' => (bool) $p->trust_email,
            'jit_enabled' => (bool) $p->jit_enabled,
            'default_role' => $p->default_role,
            'oidc' => [
                'issuer' => $p->oidc['issuer'] ?? null,
                'client_id' => $p->oidc['client_id'] ?? null,
                'scopes' => $p->oidc['scopes'] ?? [],
                'token_auth' => $p->oidc['token_auth'] ?? 'client_secret_basic',
                'userinfo' => (bool) ($p->oidc['userinfo'] ?? false),
            ],
            'client_secret_set' => $p->getRawOriginal('oidc_client_secret') !== null,
            'client_secret_hint' => $p->oidc_secret_hint,
            'redirect_uri' => $request->getSchemeAndHttpHost() . '/api/identity/oidc/' . $p->slug . '/callback',
            'last_login_at' => $p->last_login_at?->toIso8601String(),
            'last_error' => $p->last_error,
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }
}
