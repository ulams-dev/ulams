<?php

namespace Ulams\Identity\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Ulams\Auth\Http\Resources\ApiTokenCreatedResource;
use Ulams\Auth\Http\Resources\ApiTokenResource;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Identity\Enums\IdentityPermissionEnum;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\Scim\ScimTokenService;

/**
 * SCIM tokens of one identity provider (permission `identity_scim_manage`). The token itself is in the create
 * response only.
 *
 * @see ScimTokenSpec for the OpenAPI description
 */
class ScimTokenController extends UlamsBaseController
{
    public function __construct(private readonly ScimTokenService $tokens)
    {
    }

    public function index(Request $request, string $provider): JsonResponse
    {
        $this->authorizeManage($request);

        return $this->sendResponse(ApiTokenResource::collection($this->tokens->list($this->find($provider)))->toArray($request), 'SCIM tokens');
    }

    public function store(Request $request, string $provider): JsonResponse
    {
        $this->authorizeManage($request);
        $model = $this->find($provider);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'expires_in_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);
        try {
            $issued = $this->tokens->issue($model, $data['name'], (int) ($data['expires_in_days'] ?? 365));
        } catch (InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), 422);
        }

        return $this->sendResponse(ApiTokenCreatedResource::make($issued)->toArray($request), 'SCIM token created. Copy it now: it is shown only once.', 201);
    }

    public function destroy(Request $request, string $provider, string $token): JsonResponse
    {
        $this->authorizeManage($request);
        $model = $this->find($provider);
        $meta = $this->tokens->find($model, $token);
        abort_if($meta === null, 404);
        $this->tokens->revoke($model, $meta);

        return $this->sendSuccess('SCIM token revoked.');
    }

    private function find(string $id): IdentityProvider
    {
        abort_unless(Str::isUuid($id), 404);

        return IdentityProvider::query()->whereKey(strtolower($id))->firstOrFail();
    }

    private function authorizeManage(Request $request): void
    {
        if (!$request->user()?->can(IdentityPermissionEnum::SCIM_MANAGE)) {
            throw new AccessDeniedHttpException('SCIM tokens need the identity_scim_manage permission.');
        }
    }
}
