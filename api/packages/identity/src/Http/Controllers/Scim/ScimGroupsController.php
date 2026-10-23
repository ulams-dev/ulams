<?php

namespace Ulams\Identity\Http\Controllers\Scim;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ulams\Auth\Models\Group;
use Ulams\Identity\Models\ExternalId;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\Scim\GroupMapper;
use Ulams\Identity\Services\Scim\GroupProvisioner;
use Ulams\Identity\Services\Scim\PatchApplier;
use Ulams\Identity\Services\Scim\Schemas;
use Ulams\Identity\Services\Scim\ScimDirectory;
use Ulams\Identity\Services\Scim\ScimError;
use Ulams\Identity\Services\Scim\ScimPresenter;

/**
 * SCIM 2.0 Groups (RFC 7644 3.2 to 3.6). Operations are documented in ScimSpec.
 */
class ScimGroupsController extends ScimController
{
    public function __construct(private readonly GroupProvisioner $provisioner, private readonly ScimPresenter $presenter)
    {
    }

    /** @return array<string,array<string,mixed>> */
    private function filterable(IdentityProvider $provider): array
    {
        return [
            'id' => ['col' => 'groups.id', 'type' => 'int'],
            'displayname' => ['col' => 'groups.name', 'type' => 'string'],
            'externalid' => ['col' => 'x.external_id', 'type' => 'string', 'exact' => true],
            // only the members this provider provisioned count, so a filter cannot probe other accounts
            'members.value' => ['type' => 'member', 'scope' => [
                'sql' => " AND gu.user_id IN (SELECT local_id FROM identity_external_ids WHERE provider_id = ? AND resource_type = 'user' AND deleted_at IS NULL)",
                'bindings' => [$provider->getKey()],
            ]],
            'meta.lastmodified' => ['col' => 'groups.updated_at', 'type' => 'datetime'],
            'meta.created' => ['col' => 'groups.created_at', 'type' => 'datetime'],
        ];
    }

    public function index(Request $request): JsonResponse|Response
    {
        return $this->search($request, $request->query());
    }

    public function search(Request $request, ?array $params = null): JsonResponse|Response
    {
        return $this->run(function () use ($request, $params) {
            $params ??= $this->searchBody($request);
            $provider = $this->provider($request);
            $base = $this->base($request);

            return $this->listing(
                ScimDirectory::groups($provider),
                $this->filterable($provider),
                $params,
                'groups.id',
                fn (Group $group) => $this->presenter->group($provider, $group, ScimDirectory::external($provider, ExternalId::GROUP, (int) $group->getKey()), $base),
            );
        });
    }

    public function show(Request $request, string $id): JsonResponse|Response
    {
        return $this->run(function () use ($request, $id) {
            $provider = $this->provider($request);
            [$group, $ext] = ScimDirectory::group($provider, $id);
            $resource = $this->presenter->group($provider, $group, $ext, $this->base($request));
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
            [$group, $ext] = $this->provisioner->create($provider, GroupMapper::parse($this->body($request), true));

            return $this->resource($this->presenter->group($provider, $group, $ext, $this->base($request)), 201);
        });
    }

    public function replace(Request $request, string $id): JsonResponse|Response
    {
        return $this->run(function () use ($request, $id) {
            $provider = $this->provider($request);
            [$group, $ext] = ScimDirectory::group($provider, $id);
            $base = $this->base($request);
            $this->assertMatch($request, $this->presenter->group($provider, $group, $ext, $base));
            $body = $this->body($request);
            if (isset($body['id']) && (string) $body['id'] !== (string) $group->getKey()) {
                throw ScimError::mutability('The id cannot be changed.');
            }
            $group = $this->provisioner->update($provider, $group, $ext, GroupMapper::parse($body, true));

            return $this->resource($this->presenter->group($provider, $group, ScimDirectory::external($provider, ExternalId::GROUP, (int) $group->getKey()), $base));
        });
    }

    public function patch(Request $request, string $id): JsonResponse|Response
    {
        return $this->run(function () use ($request, $id) {
            $provider = $this->provider($request);
            [$group, $ext] = ScimDirectory::group($provider, $id);
            $base = $this->base($request);
            $current = $this->presenter->group($provider, $group, $ext, $base);
            $this->assertMatch($request, $current);
            $changed = self::patcher()->apply($current, $this->body($request));
            // a PATCH that empties the members must say so: a missing key means "keep", an empty list means "none"
            $changed['members'] = $changed['members'] ?? [];
            $group = $this->provisioner->update($provider, $group, $ext, GroupMapper::parse($changed, false));

            return $this->resource($this->presenter->group($provider, $group, ScimDirectory::external($provider, ExternalId::GROUP, (int) $group->getKey()), $base));
        });
    }

    public function destroy(Request $request, string $id): JsonResponse|Response
    {
        return $this->run(function () use ($request, $id) {
            $provider = $this->provider($request);
            [$group, $ext] = ScimDirectory::group($provider, $id);
            $this->assertMatch($request, $this->presenter->group($provider, $group, $ext, $this->base($request)));
            $this->provisioner->delete($provider, $group, $ext);

            return response('', 204);
        });
    }

    public static function patcher(): PatchApplier
    {
        return new PatchApplier(['members'], ['displayName', 'externalId', 'members', 'value', 'display', 'type'], [Schemas::GROUP]);
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
