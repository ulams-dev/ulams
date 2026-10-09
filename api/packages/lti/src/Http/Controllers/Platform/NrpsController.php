<?php

namespace Ulams\Lti\Http\Controllers\Platform;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ulams\Lti\Exceptions\LtiRequestException;
use Ulams\Lti\Http\Middleware\IsolateLtiBearer;
use Ulams\Lti\Platform\AgsService;
use Ulams\Lti\Platform\NrpsService;
use Ulams\Lti\Support\Lti;

/**
 * Names and Role Provisioning Services 2.0 of the platform side. Authenticated with an access token
 * from /api/lti/platform/token (scope contextmembership.readonly); the tool needs NRPS enabled in its
 * registration and the course must contain one of its links.
 */
class NrpsController extends Controller
{
    public function __construct(private readonly AgsService $ags, private readonly NrpsService $nrps)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/lti/platform/nrps/{course}",
     *     summary="NRPS: members of a course (membership container)",
     *     description="Bearer token from /api/lti/platform/token with scope https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly. Names and e-mails only when the tool registration shares them. Paged by `limit` (max 100) and `page`; a Link header with rel=next points to the next page. `role` filters by LTI role.",
     *     tags={"LTI"},
     *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", maximum=100)),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="role", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="application/vnd.ims.lti-nrps.v2.membershipcontainer+json"),
     *     @OA\Response(response=401, description="invalid token"),
     *     @OA\Response(response=403, description="scope missing or NRPS not enabled for the tool"),
     *     @OA\Response(response=404, description="the course holds no link of this tool")
     * )
     */
    public function memberships(Request $request, int $course): JsonResponse
    {
        try {
            [$tool, $granted] = $this->ags->authenticate(IsolateLtiBearer::token($request));
            AgsService::requireScope($granted, Lti::SCOPE_NRPS);
            if (!$tool->nrps_enabled) {
                throw new LtiRequestException('Names and Role Provisioning is not enabled for this tool.', 403, 'insufficient_scope');
            }
            $model = $this->ags->course($tool, $course);

            $page = max(1, (int) $request->query('page', 1));
            $limit = (int) $request->query('limit', NrpsService::DEFAULT_LIMIT);
            $limit = $limit > 0 ? min($limit, NrpsService::MAX_LIMIT) : NrpsService::DEFAULT_LIMIT;
            $role = $request->query('role');
            $role = is_string($role) && $role !== '' ? $role : null;

            $result = $this->nrps->memberships($tool, $model, $page, $limit, $role);
            $response = response()->json($result['body'])
                ->header('Content-Type', NrpsService::CONTAINER_TYPE)
                ->header('Cache-Control', 'no-store');
            if ($result['next'] !== null) {
                $response->header('Link', '<' . $this->nrps->url($model, $result['next']['page'], $result['next']['limit'], $role) . '>; rel="next"');
            }

            return $response;
        } catch (LtiRequestException $e) {
            return response()->json(['error' => $e->oauthError, 'error_description' => $e->getMessage()], $e->status)
                ->header('Cache-Control', 'no-store');
        }
    }
}
