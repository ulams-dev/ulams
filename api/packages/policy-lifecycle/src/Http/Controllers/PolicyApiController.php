<?php

namespace Ulams\PolicyLifecycle\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ulams\PolicyLifecycle\Exceptions\PolicyException;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Services\PolicyAccess;

abstract class PolicyApiController extends Controller
{
    public function __construct(protected PolicyAccess $access)
    {
    }


    /** The user needs `policy_manage` or `policy_owner` at all. */
    protected function guardUse(Request $request): void
    {
        abort_unless($this->access->mayUse($request->user()), 403);
    }

    /** Loads the policy and checks that the user may act on this one. */
    protected function policyFor(Request $request, string $id): Policy
    {
        $this->guardUse($request);
        $policy = Policy::query()->findOrFail($id);
        abort_unless($this->access->allows($request->user(), $policy), 403);

        return $policy;
    }

    protected function ok(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        $body = ['success' => true, 'data' => $data, 'message' => 'OK'];
        if ($meta) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    protected function fail(PolicyException $e): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
    }
}
