<?php

namespace Ulams\Tenancy\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Tenancy\Http\Controllers\Swagger\EntryPointResolveSwagger;
use Ulams\Tenancy\Services\EntryPointResolver;

/**
 * Which academy and entry point serves a host (ADR 0195). Public, platform hosts only, throttled per address; an
 * unknown, pending, disabled or malformed host is one and the same 404.
 */
class EntryPointResolveController extends UlamsBaseController implements EntryPointResolveSwagger
{
    public function __construct(private readonly EntryPointResolver $resolver)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $host = $request->query('host');
        $tenant = $request->query('tenant');
        $key = $request->query('key');

        $entry = match (true) {
            is_string($host) && $host !== '' && $tenant === null && $key === null => $this->resolver->byHost($host),
            is_string($tenant) && is_string($key) && $host === null => $this->resolver->byKey($tenant, $key),
            default => null,
        };

        return $entry === null
            ? $this->sendError(__('Not found.'), 404)
            : $this->sendResponse($entry, __('Entry point'));
    }
}
