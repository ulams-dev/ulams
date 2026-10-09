<?php

namespace Ulams\Lti\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Ulams\Lti\Services\KeyService;

class JwksController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/lti/jwks",
     *     summary="Public key set of this tenant (LTI platform and tool side)",
     *     description="Also served at /.well-known/jwks.json. Contains the next, active and recently retired keys.",
     *     tags={"LTI"},
     *     @OA\Response(response=200, description="JWKS")
     * )
     */
    public function __invoke(KeyService $keys): JsonResponse
    {
        $keys->ensureKeys();

        return response()->json($keys->jwks())->header('Cache-Control', 'public, max-age=300');
    }
}
