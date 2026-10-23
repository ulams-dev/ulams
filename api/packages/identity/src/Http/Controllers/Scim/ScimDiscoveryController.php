<?php

namespace Ulams\Identity\Http\Controllers\Scim;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ulams\Identity\Services\Scim\Schemas;
use Ulams\Identity\Services\Scim\ScimError;

/** ServiceProviderConfig, ResourceTypes and Schemas (RFC 7644 4). Operations are documented in ScimSpec. */
class ScimDiscoveryController extends ScimController
{
    public function config(Request $request): JsonResponse|Response
    {
        return $this->run(fn () => $this->resource(Schemas::serviceProviderConfig($this->base($request), config('ulams_identity.scim.docs_url'))));
    }

    public function resourceTypes(Request $request): JsonResponse|Response
    {
        return $this->run(fn () => $this->listOf(Schemas::resourceTypes($this->base($request))));
    }

    public function resourceType(Request $request, string $id): JsonResponse|Response
    {
        return $this->run(fn () => $this->one(Schemas::resourceTypes($this->base($request)), $id, 'ResourceType'));
    }

    public function schemas(Request $request): JsonResponse|Response
    {
        return $this->run(fn () => $this->listOf(Schemas::schemas($this->base($request))));
    }

    public function schema(Request $request, string $id): JsonResponse|Response
    {
        return $this->run(fn () => $this->one(Schemas::schemas($this->base($request)), $id, 'Schema'));
    }

    /** @param list<array<string,mixed>> $items */
    private function listOf(array $items): JsonResponse
    {
        return response()->json(
            ['schemas' => [Schemas::LIST], 'totalResults' => count($items), 'startIndex' => 1, 'itemsPerPage' => count($items), 'Resources' => $items],
            200,
            ['Content-Type' => self::CONTENT_TYPE],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /** @param list<array<string,mixed>> $items */
    private function one(array $items, string $id, string $type): JsonResponse
    {
        foreach ($items as $item) {
            if ($item['id'] === $id) {
                return $this->resource($item);
            }
        }

        throw ScimError::notFound($type, mb_substr($id, 0, 100));
    }
}
