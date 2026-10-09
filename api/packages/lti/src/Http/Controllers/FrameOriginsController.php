<?php

namespace Ulams\Lti\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Ulams\Lti\Models\LtiTool;

class FrameOriginsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/lti/frame-origins",
     *     summary="Origins of the enabled external tools of this tenant",
     *     description="For the front's Content Security Policy: the `frame-src` of a learner page must name the origins a tool launch loads (its OIDC login, launch and deep-linking URLs). Public and cached for 5 minutes; it reveals only the origins of tools the tenant registered for its learners.",
     *     tags={"LTI"},
     *     @OA\Response(
     *         response=200,
     *         description="Sorted, unique origins (`scheme://host[:port]`)",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="string", example="https://www.geogebra.org")),
     *         )
     *     )
     * )
     */
    public function __invoke(): JsonResponse
    {
        $origins = [];

        foreach (LtiTool::query()->where('enabled', true)->get(['oidc_login_url', 'launch_url', 'deep_linking_url']) as $tool) {
            foreach ([$tool->oidc_login_url, $tool->launch_url, $tool->deep_linking_url] as $url) {
                if (($origin = self::originOf($url)) !== null) {
                    $origins[$origin] = true;
                }
            }
        }
        $origins = array_keys($origins);
        sort($origins);

        return response()
            ->json(['success' => true, 'message' => 'LTI frame origins', 'data' => $origins])
            ->header('Cache-Control', 'public, max-age=300');
    }

    /** `https://tool.example:8443/x?y` to `https://tool.example:8443`; null for anything that is not http(s). */
    public static function originOf(?string $url): ?string
    {
        $parts = is_string($url) ? parse_url(trim($url)) : false;
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }
        // keep the origin free of anything a CSP source list would treat as syntax
        if (!preg_match('/^[a-z0-9.-]+$/i', $parts['host'])) {
            return null;
        }

        return strtolower($parts['scheme']) . '://' . strtolower($parts['host']) . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');
    }
}
