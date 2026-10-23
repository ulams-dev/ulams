<?php

namespace Ulams\Identity\Http\Controllers\Scim;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ulams\Auth\Models\User;
use Ulams\Identity\Models\ExternalId;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\Scim\PatchApplier;
use Ulams\Identity\Services\Scim\Schemas;
use Ulams\Identity\Services\Scim\ScimDirectory;
use Ulams\Identity\Services\Scim\ScimError;
use Ulams\Identity\Services\Scim\ScimPresenter;
use Ulams\Identity\Services\Scim\UserMapper;
use Ulams\Identity\Services\Scim\UserProvisioner;

/**
 * SCIM 2.0 Users (RFC 7644 3.2 to 3.6). Operations are documented in ScimSpec.
 */
class ScimUsersController extends ScimController
{
    /** @var array<string,array<string,mixed>> */
    private const FILTERABLE = [
        'id' => ['col' => 'users.id', 'type' => 'int'],
        'username' => ['col' => 'x.user_name_key', 'type' => 'string'],
        'externalid' => ['col' => 'x.external_id', 'type' => 'string', 'exact' => true],
        'emails.value' => ['col' => 'users.email', 'type' => 'string'],
        'emails.type' => ['type' => 'const', 'value' => 'work'],
        'emails.primary' => ['type' => 'const', 'value' => true],
        'active' => ['col' => 'users.is_active', 'type' => 'bool'],
        'name.givenname' => ['col' => 'users.first_name', 'type' => 'string'],
        'name.familyname' => ['col' => 'users.last_name', 'type' => 'string'],
        'meta.lastmodified' => ['col' => 'users.updated_at', 'type' => 'datetime'],
        'meta.created' => ['col' => 'users.created_at', 'type' => 'datetime'],
    ];

    public function __construct(private readonly UserProvisioner $provisioner, private readonly ScimPresenter $presenter)
    {
    }

    public function index(Request $request): JsonResponse|Response
    {
        return $this->run(fn () => $this->search($request, $request->query()));
    }

    public function search(Request $request, ?array $params = null): JsonResponse|Response
    {
        return $this->run(function () use ($request, $params) {
            $params ??= $this->searchBody($request);
            $provider = $this->provider($request);
            $base = $this->base($request);

            return $this->listing(
                ScimDirectory::users($provider),
                self::FILTERABLE,
                $params,
                'users.id',
                fn (User $user) => $this->presenter->user($provider, $user, ScimDirectory::external($provider, ExternalId::USER, (int) $user->getKey()), $base),
            );
        });
    }

    public function show(Request $request, string $id): JsonResponse|Response
    {
        return $this->run(function () use ($request, $id) {
            $provider = $this->provider($request);
            [$user, $ext] = ScimDirectory::user($provider, $id);
            $resource = $this->presenter->user($provider, $user, $ext, $this->base($request));
            if ($this->notModified($request, $resource)) {
                return response('', 304, ['ETag' => $resource['meta']['version']]);
            }

            return $this->resource($this->project($resource, $request->query('attributes'), $request->query('excludedAttributes')));
        });
    }

    public function store(Request $request): JsonResponse|Response
    {
        return $this->run(function () use ($request) {
            $provider = $this->provider($request);
            $input = UserMapper::parse($this->body($request), true);
            [$user, $ext] = $this->provisioner->create($provider, $input);

            return $this->resource($this->presenter->user($provider, $user, $ext, $this->base($request)), 201);
        });
    }

    public function replace(Request $request, string $id): JsonResponse|Response
    {
        return $this->run(function () use ($request, $id) {
            $provider = $this->provider($request);
            [$user, $ext] = ScimDirectory::user($provider, $id);
            $base = $this->base($request);
            $this->assertMatch($request, $this->presenter->user($provider, $user, $ext, $base));
            $body = $this->body($request);
            if (isset($body['id']) && (string) $body['id'] !== (string) $user->getKey()) {
                throw ScimError::mutability('The id cannot be changed.');
            }
            $user = $this->provisioner->update($provider, $user, $ext, UserMapper::parse($body, true));

            return $this->resource($this->presenter->user($provider, $user, ScimDirectory::external($provider, ExternalId::USER, (int) $user->getKey()), $base));
        });
    }

    public function patch(Request $request, string $id): JsonResponse|Response
    {
        return $this->run(function () use ($request, $id) {
            $provider = $this->provider($request);
            [$user, $ext] = ScimDirectory::user($provider, $id);
            $base = $this->base($request);
            $current = $this->presenter->user($provider, $user, $ext, $base);
            $this->assertMatch($request, $current);
            // every operation is applied to a copy of the representation; nothing is written unless all of them work
            $changed = self::patcher()->apply($current, $this->body($request));
            $user = $this->provisioner->update($provider, $user, $ext, UserMapper::parse($changed, false));

            return $this->resource($this->presenter->user($provider, $user, ScimDirectory::external($provider, ExternalId::USER, (int) $user->getKey()), $base));
        });
    }

    public function destroy(Request $request, string $id): JsonResponse|Response
    {
        return $this->run(function () use ($request, $id) {
            $provider = $this->provider($request);
            [$user, $ext] = ScimDirectory::user($provider, $id);
            $this->assertMatch($request, $this->presenter->user($provider, $user, $ext, $this->base($request)));
            $this->provisioner->delete($provider, $user, $ext);

            return response('', 204);
        });
    }

    public static function patcher(): PatchApplier
    {
        return new PatchApplier(
            ['emails', 'phonenumbers', 'addresses', 'groups'],
            ['userName', 'externalId', 'name', 'displayName', 'title', 'emails', 'active', 'givenName', 'familyName', 'formatted', 'value', 'type', 'primary',
                'employeeNumber', 'costCenter', 'organization', 'division', 'department', 'manager'],
            [Schemas::ENTERPRISE, Schemas::USER],
        );
    }

    /** @return array<string,mixed> */
    private function searchBody(Request $request): array
    {
        $body = $this->body($request);
        $schemas = $body['schemas'] ?? null;
        if (!is_array($schemas) || !in_array(Schemas::SEARCH, $schemas, true)) {
            throw ScimError::invalidSyntax('The request must list ' . Schemas::SEARCH . ' in schemas.');
        }
        foreach (['attributes', 'excludedAttributes'] as $key) {
            if (isset($body[$key]) && is_array($body[$key])) {
                $body[$key] = implode(',', array_filter($body[$key], 'is_string'));
            }
        }

        return $body;
    }
}
