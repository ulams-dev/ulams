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
use Ulams\Identity\Services\Saml\SamlContext;
use Ulams\Identity\Services\Saml\SamlService;
use Ulams\Identity\Services\Saml\SamlXml;

/**
 * Identity providers of the tenant (admin only, permission `identity_provider_manage`). The client secret is
 * write-only: responses say whether one is set and, for long secrets, its last four characters.
 *
 * @OA\Schema(schema="IdentityProvider", type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="slug", type="string", description="Used in the sign-in URLs; fixed after creation"),
 *     @OA\Property(property="name", type="string", description="Shown on the sign-in button"),
 *     @OA\Property(property="protocol", type="string", enum={"oidc","saml"}),
 *     @OA\Property(property="enabled", type="boolean"),
 *     @OA\Property(property="email_domains", type="array", @OA\Items(type="string"), description="Domains of the organisation"),
 *     @OA\Property(property="trust_email", type="boolean", description="The provider is authoritative for e-mail addresses of email_domains: an existing non-privileged account with a verified address there is linked on first sign-in"),
 *     @OA\Property(property="jit_enabled", type="boolean", description="Create an account on first sign-in"),
 *     @OA\Property(property="default_role", type="string", enum={"student","tutor"}),
 *     @OA\Property(property="oidc", type="object", nullable=true, description="OpenID Connect providers only",
 *         @OA\Property(property="issuer", type="string", format="uri"),
 *         @OA\Property(property="client_id", type="string"),
 *         @OA\Property(property="scopes", type="array", @OA\Items(type="string")),
 *         @OA\Property(property="token_auth", type="string", enum={"client_secret_basic","client_secret_post"}),
 *         @OA\Property(property="userinfo", type="boolean")),
 *     @OA\Property(property="saml", type="object", nullable=true, description="SAML providers only. Private keys never leave the API",
 *         @OA\Property(property="entity_id", type="string", nullable=true, description="The identity provider's entity id"),
 *         @OA\Property(property="sso_url", type="string", format="uri", nullable=true),
 *         @OA\Property(property="slo_url", type="string", format="uri", nullable=true),
 *         @OA\Property(property="metadata_url", type="string", format="uri", nullable=true, description="Refreshed daily; new signing certificates are added automatically"),
 *         @OA\Property(property="name_id_format", type="string", nullable=true),
 *         @OA\Property(property="want_encrypted_assertions", type="boolean"),
 *         @OA\Property(property="allow_sha1", type="boolean", description="Accept SHA-1 signatures (legacy). Weak: every such sign-in is audited"),
 *         @OA\Property(property="attribute_map", type="object", description="Attribute names for email, first_name, last_name, name and subject"),
 *         @OA\Property(property="idp_certificates", type="array", description="Signing certificates trusted for this provider", @OA\Items(type="object",
 *             @OA\Property(property="fingerprint", type="string", description="SHA-256 of the certificate"),
 *             @OA\Property(property="not_before", type="string", format="date-time", nullable=true),
 *             @OA\Property(property="not_after", type="string", format="date-time", nullable=true),
 *             @OA\Property(property="added_at", type="string", format="date-time"),
 *             @OA\Property(property="source", type="string", enum={"metadata","admin"}))),
 *         @OA\Property(property="sp", type="object", description="What to give the identity provider",
 *             @OA\Property(property="entity_id", type="string", format="uri"),
 *             @OA\Property(property="metadata_url", type="string", format="uri"),
 *             @OA\Property(property="acs_url", type="string", format="uri"),
 *             @OA\Property(property="slo_url", type="string", format="uri"),
 *             @OA\Property(property="certificate_fingerprint", type="string", nullable=true),
 *             @OA\Property(property="next_certificate_fingerprint", type="string", nullable=true, description="A prepared key pair, already published in the metadata"))),
 *     @OA\Property(property="scim", type="object", description="SCIM 2.0 provisioning with this provider",
 *         @OA\Property(property="base_url", type="string", format="uri", description="Tenant URL to enter in the identity provider's SCIM settings"),
 *         @OA\Property(property="group_roles", type="object", description="Display name of a SCIM group (lower case) to the role its members get", @OA\AdditionalProperties(type="string", enum={"student","tutor"})),
 *         @OA\Property(property="active_tokens", type="integer", description="Active SCIM tokens (at most two)")),
 *     @OA\Property(property="metadata_refreshed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="client_secret_set", type="boolean"),
 *     @OA\Property(property="client_secret_hint", type="string", nullable=true),
 *     @OA\Property(property="redirect_uri", type="string", format="uri", description="Register this redirect URI at the provider"),
 *     @OA\Property(property="last_login_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="last_error", type="string", nullable=true, description="Reason code of the last failed sign-in"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"))
 * @OA\Schema(schema="IdentityProviderInput", type="object",
 *     @OA\Property(property="protocol", type="string", enum={"oidc","saml"}, description="Fixed at creation (default oidc); ignored on update"),
 *     @OA\Property(property="slug", type="string", description="Lowercase letters, digits and hyphens; required on create, ignored on update"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="enabled", type="boolean"),
 *     @OA\Property(property="email_domains", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="trust_email", type="boolean"),
 *     @OA\Property(property="jit_enabled", type="boolean"),
 *     @OA\Property(property="default_role", type="string", enum={"student","tutor"}),
 *     @OA\Property(property="scim", type="object", @OA\Property(property="group_roles", type="object", nullable=true, description="Display name of a SCIM group to student or tutor: members of such a group get the role while they are members (replaces the whole mapping; admin cannot be mapped)", @OA\AdditionalProperties(type="string", enum={"student","tutor"}))),
 *     @OA\Property(property="oidc", type="object",
 *         @OA\Property(property="issuer", type="string", format="uri"),
 *         @OA\Property(property="client_id", type="string"),
 *         @OA\Property(property="scopes", type="array", @OA\Items(type="string")),
 *         @OA\Property(property="token_auth", type="string", enum={"client_secret_basic","client_secret_post"}),
 *         @OA\Property(property="userinfo", type="boolean")),
 *     @OA\Property(property="client_secret", type="string", description="Write-only; stored encrypted"),
 *     @OA\Property(property="saml", type="object", description="SAML providers: give metadata_url or metadata_xml (entity id, endpoints and certificates are read from it), or the fields by hand",
 *         @OA\Property(property="metadata_url", type="string", format="uri"),
 *         @OA\Property(property="metadata_xml", type="string", description="Uploaded IdP metadata (at most 2 MB)"),
 *         @OA\Property(property="entity_id", type="string"),
 *         @OA\Property(property="sso_url", type="string", format="uri"),
 *         @OA\Property(property="slo_url", type="string", format="uri"),
 *         @OA\Property(property="name_id_format", type="string"),
 *         @OA\Property(property="want_encrypted_assertions", type="boolean"),
 *         @OA\Property(property="allow_sha1", type="boolean"),
 *         @OA\Property(property="idp_certificates", type="array", description="Replaces the trusted signing certificates (PEM or base64)", @OA\Items(type="string")),
 *         @OA\Property(property="attribute_map", type="object",
 *             @OA\Property(property="email", type="string"), @OA\Property(property="first_name", type="string"), @OA\Property(property="last_name", type="string"),
 *             @OA\Property(property="name", type="string"), @OA\Property(property="subject", type="string"))))
 * @OA\Schema(schema="IdentityMetadataRefresh", type="object",
 *     @OA\Property(property="status", type="string", enum={"unchanged","updated","attention","failed"}, description="attention: a change needs an administrator (another entity id, certificates unrelated to the trusted ones)"),
 *     @OA\Property(property="added", type="array", @OA\Items(type="string"), description="SHA-256 fingerprints of certificates added"),
 *     @OA\Property(property="removed", type="array", @OA\Items(type="string"), description="SHA-256 fingerprints of certificates removed"),
 *     @OA\Property(property="reason", type="string", nullable=true))
 *
 * @OA\Get(path="/api/admin/identity/providers", operationId="identity.providers.list", summary="Identity providers of this academy", tags={"Admin Identity"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="Providers", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/IdentityProvider")))),
 *     @OA\Response(response=403, description="Needs identity_provider_manage"))
 * @OA\Post(path="/api/admin/identity/providers", operationId="identity.providers.create", summary="Add an OpenID Connect or SAML identity provider", tags={"Admin Identity"}, security={{"passport": {}}},
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
 * @OA\Post(path="/api/admin/identity/providers/{provider}/metadata/refresh", operationId="identity.providers.metadata.refresh", summary="Fetch the SAML identity provider metadata again", tags={"Admin Identity"}, security={{"passport": {}}},
 *     @OA\Parameter(name="provider", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="accept_changes", type="boolean", description="Apply a changed entity id and replace the trusted certificates with the ones in the metadata (an administrator's decision)"))),
 *     @OA\Response(response=200, description="Outcome", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/IdentityMetadataRefresh"))),
 *     @OA\Response(response=404, description="Not found"),
 *     @OA\Response(response=422, description="Not a SAML provider with a metadata URL"))
 * @OA\Post(path="/api/admin/identity/providers/{provider}/sp-keys/rotate", operationId="identity.providers.spkeys.rotate", summary="Prepare or activate the next SAML service provider key pair", tags={"Admin Identity"}, security={{"passport": {}}},
 *     @OA\Parameter(name="provider", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"step"}, @OA\Property(property="step", type="string", enum={"prepare","promote"}, description="prepare: generate the next pair and publish it in the SP metadata; promote: make it the current pair once the identity provider has imported it"))),
 *     @OA\Response(response=200, description="Rotated", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", type="object", @OA\Property(property="step", type="string"), @OA\Property(property="fingerprint", type="string", description="SHA-256 of the certificate")))),
 *     @OA\Response(response=404, description="Not found"),
 *     @OA\Response(response=422, description="Not a SAML provider, or nothing prepared to promote"))
 * @OA\Delete(path="/api/admin/identity/providers/{provider}", operationId="identity.providers.delete", summary="Delete an identity provider and its account links (accounts stay)", tags={"Admin Identity"}, security={{"passport": {}}},
 *     @OA\Parameter(name="provider", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"))),
 *     @OA\Response(response=404, description="Not found"))
 */
class ProviderController extends UlamsBaseController
{
    public function __construct(private readonly ProviderService $providers, private readonly SamlService $saml)
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

    public function refreshMetadata(Request $request, string $provider): JsonResponse
    {
        $this->authorizeManage($request);
        $model = $this->find($provider);
        $data = $request->validate(['accept_changes' => ['sometimes', 'boolean']]);
        if ($model->protocol !== IdentityProvider::SAML || (string) (((array) $model->saml)['metadata_url'] ?? '') === '') {
            return $this->sendError('Only SAML providers with a metadata URL can be refreshed.', 422);
        }

        return $this->sendResponse($this->providers->refreshMetadata($model, (bool) ($data['accept_changes'] ?? false), 'user'), 'Metadata refreshed.');
    }

    public function rotateSpKeys(Request $request, string $provider): JsonResponse
    {
        $this->authorizeManage($request);
        $model = $this->find($provider);
        $data = $request->validate(['step' => ['required', Rule::in(['prepare', 'promote'])]]);

        return $this->sendResponse($this->providers->rotateSpKeys($model, $data['step']), 'Service provider keys rotated.');
    }

    /** @return array<string,mixed> */
    private function rules(bool $creating): array
    {
        $saml = $creating ? request()->input('protocol') === IdentityProvider::SAML : (($this->routeProvider()?->protocol) === IdentityProvider::SAML);
        $required = $creating ? 'required' : 'sometimes';
        $urlRule = function (string $attribute, mixed $value, \Closure $fail): void {
            $parts = is_string($value) ? parse_url($value) : false;
            $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
            $okScheme = $scheme === 'https' || ($scheme === 'http' && config('ulams_identity.allow_insecure_urls'));
            if (!is_array($parts) || !$okScheme || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || filter_var($value, FILTER_VALIDATE_URL) === false) {
                $fail('The URL must be an https URL without credentials or fragment.');
            }
        };
        $issuerRule = function (string $attribute, mixed $value, \Closure $fail): void {
            $parts = is_string($value) ? parse_url($value) : false;
            $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
            $okScheme = $scheme === 'https' || ($scheme === 'http' && config('ulams_identity.allow_insecure_urls'));
            if (!is_array($parts) || !$okScheme || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                $fail('The issuer must be an https URL without credentials, query or fragment.');
            }
        };

        $samlRules = [
            'saml' => ['sometimes', 'array'],
            'saml.metadata_url' => ['sometimes', 'nullable', 'string', 'max:2048', $urlRule],
            'saml.metadata_xml' => ['sometimes', 'nullable', 'string', 'max:' . (int) config('ulams_identity.saml.metadata_max_bytes')],
            'saml.entity_id' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'saml.sso_url' => ['sometimes', 'nullable', 'string', 'max:2048', $urlRule],
            'saml.slo_url' => ['sometimes', 'nullable', 'string', 'max:2048', $urlRule],
            'saml.name_id_format' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^urn:oasis:names:tc:SAML:[0-9.]+:nameid-format:[A-Za-z0-9]+$/'],
            'saml.want_encrypted_assertions' => ['sometimes', 'boolean'],
            'saml.allow_sha1' => ['sometimes', 'boolean'],
            'saml.idp_certificates' => ['sometimes', 'array', 'min:1', 'max:10'],
            'saml.idp_certificates.*' => ['string', 'max:8192'],
            'saml.attribute_map' => ['sometimes', 'array'],
            'saml.attribute_map.email' => ['sometimes', 'nullable', 'string', 'max:255'],
            'saml.attribute_map.first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'saml.attribute_map.last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'saml.attribute_map.name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'saml.attribute_map.subject' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
        $protocolRules = $saml ? $samlRules : [
            'oidc' => [$required, 'array'],
            'oidc.issuer' => [$required, 'string', 'max:255', $issuerRule],
            'oidc.client_id' => [$required, 'string', 'min:1', 'max:255'],
            'oidc.scopes' => ['sometimes', 'array', 'max:20'],
            'oidc.scopes.*' => ['string', 'max:100', 'regex:/^[\x21\x23-\x5B\x5D-\x7E]+$/'],
            'oidc.token_auth' => ['sometimes', Rule::in(['client_secret_basic', 'client_secret_post'])],
            'oidc.userinfo' => ['sometimes', 'boolean'],
            'client_secret' => ['sometimes', 'nullable', 'string', 'min:1', 'max:512'],
        ];

        return ($creating ? ['slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/', Rule::unique('identity_providers', 'slug')], 'protocol' => ['sometimes', Rule::in(['oidc', 'saml'])]] : []) + $protocolRules + [
            // fixed after creation: on update the key is not validated, so it never reaches the service
            'name' => [$required, 'string', 'min:1', 'max:120'],
            'enabled' => ['sometimes', 'boolean'],
            'email_domains' => ['sometimes', 'array', 'max:20'],
            'email_domains.*' => ['string', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i'],
            'trust_email' => ['sometimes', 'boolean'],
            'jit_enabled' => ['sometimes', 'boolean'],
            'default_role' => ['sometimes', Rule::in(['student', 'tutor'])],
            'scim' => ['sometimes', 'array'],
            'scim.group_roles' => ['sometimes', 'nullable', 'array', 'max:100', function (string $attribute, mixed $value, \Closure $fail): void {
                foreach ((array) $value as $name => $role) {
                    if (!is_string($name) || trim($name) === '' || mb_strlen($name) > 255 || !in_array($role, \Ulams\Identity\Services\Scim\GroupRoleMapper::MAPPABLE, true)) {
                        $fail('Map the display name of a group to student or tutor; no group can grant another role.');
                    }
                }
            }],
        ];
    }

    /** The provider of the current route (update), to pick the rules of its protocol. */
    private function routeProvider(): ?IdentityProvider
    {
        $id = request()->route('provider');

        return is_string($id) && Str::isUuid($id) ? IdentityProvider::query()->whereKey(strtolower($id))->first() : null;
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
            'oidc' => $p->protocol === IdentityProvider::OIDC ? [
                'issuer' => $p->oidc['issuer'] ?? null,
                'client_id' => $p->oidc['client_id'] ?? null,
                'scopes' => $p->oidc['scopes'] ?? [],
                'token_auth' => $p->oidc['token_auth'] ?? 'client_secret_basic',
                'userinfo' => (bool) ($p->oidc['userinfo'] ?? false),
            ] : null,
            'saml' => $p->protocol === IdentityProvider::SAML ? self::presentSaml($p, $request) : null,
            'scim' => [
                'base_url' => $request->getSchemeAndHttpHost() . '/api/scim/v2',
                'group_roles' => (object) ($p->scim_group_roles ?? []),
                'active_tokens' => app(\Ulams\Identity\Services\Scim\ScimTokenService::class)->list($p)->filter(fn ($m) => !$m->token?->revoked && $m->token?->expires_at?->isFuture())->count(),
            ],
            'metadata_refreshed_at' => $p->metadata_refreshed_at?->toIso8601String(),
            'client_secret_set' => $p->getRawOriginal('oidc_client_secret') !== null,
            'client_secret_hint' => $p->oidc_secret_hint,
            'redirect_uri' => $p->protocol === IdentityProvider::OIDC ? $request->getSchemeAndHttpHost() . '/api/identity/oidc/' . $p->slug . '/callback' : null,
            'last_login_at' => $p->last_login_at?->toIso8601String(),
            'last_error' => $p->last_error,
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private static function presentSaml(IdentityProvider $p, Request $request): array
    {
        $saml = (array) $p->saml;
        $urls = app(SamlService::class)->spUrls($p, SamlContext::fromRequest($request));
        $fingerprint = static fn (?string $cert): ?string => $cert !== null && $cert !== '' ? SamlXml::fingerprint($cert) : null;

        return [
            'entity_id' => $saml['entity_id'] ?? null,
            'sso_url' => $saml['sso_url'] ?? null,
            'slo_url' => $saml['slo_url'] ?? null,
            'metadata_url' => $saml['metadata_url'] ?? null,
            'name_id_format' => $saml['name_id_format'] ?? null,
            'want_encrypted_assertions' => (bool) ($saml['want_encrypted_assertions'] ?? false),
            'allow_sha1' => (bool) ($saml['allow_sha1'] ?? false),
            'attribute_map' => (object) ($saml['attribute_map'] ?? []),
            'idp_certificates' => array_map(fn (array $c) => [
                'fingerprint' => $c['fingerprint'], 'not_before' => $c['not_before'] ?? null, 'not_after' => $c['not_after'] ?? null,
                'added_at' => $c['added_at'] ?? null, 'source' => $c['source'] ?? 'metadata',
            ], (array) $p->saml_idp_certs),
            'sp' => $urls + [
                'certificate_fingerprint' => $fingerprint($p->saml_sp_cert),
                'next_certificate_fingerprint' => $fingerprint($p->saml_sp_next_cert),
            ],
        ];
    }
}
