<?php

namespace Ulams\Lti\Http\Controllers\Tool;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Ulams\Lti\Exceptions\LtiRequestException;
use Ulams\Lti\Http\Controllers\RendersLtiResponses;
use Ulams\Lti\Support\Lti;
use Ulams\Lti\Tool\ToolLaunchService;

/**
 * Tool side: other LMSs (Moodle, Canvas, ...) launch our courses (ADR 0012).
 */
class ToolController extends Controller
{
    use RendersLtiResponses;

    public function __construct(private readonly ToolLaunchService $tool)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/lti/tool/login",
     *     summary="OIDC login initiation from a registered platform (also POST)",
     *     tags={"LTI"},
     *     @OA\Response(response=302, description="redirect to the platform's OIDC auth URL"),
     *     @OA\Response(response=400, description="unknown platform or missing login_hint")
     * )
     */
    public function login(Request $request): RedirectResponse|Response
    {
        try {
            return redirect()->away($this->tool->loginRedirect($request->all()));
        } catch (LtiRequestException $e) {
            return $this->htmlError($e);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/lti/tool/launch",
     *     summary="LTI launch (resource link or deep-linking request) from a registered platform",
     *     description="Resource link: maps the platform user, grants course access and redirects to the front with a one-time code. Deep linking: shows the course picker.",
     *     tags={"LTI"},
     *     @OA\Response(response=302, description="redirect to the front landing page"),
     *     @OA\Response(response=200, description="course picker (deep linking)"),
     *     @OA\Response(response=401, description="invalid launch")
     * )
     */
    public function launch(Request $request): RedirectResponse|Response
    {
        try {
            $result = $this->tool->launch($request->all());
        } catch (LtiRequestException $e) {
            return $this->htmlError($e);
        }

        if ($result['type'] === 'deep_link') {
            return response()->view('lti::picker', [
                'action' => Lti::url('api/lti/tool/deep-link'),
                'formToken' => $result['form_token'],
                'courses' => $result['courses'],
            ])->header('Cache-Control', 'no-store');
        }

        return redirect()->away($result['redirect']);
    }

    /**
     * @OA\Post(
     *     path="/api/lti/tool/deep-link",
     *     summary="Return the courses picked in the deep-linking picker to the platform",
     *     tags={"LTI"},
     *     @OA\Response(response=200, description="auto-submitting form with the LtiDeepLinkingResponse JWT"),
     *     @OA\Response(response=410, description="the deep-linking session expired")
     * )
     */
    public function deepLink(Request $request): Response
    {
        try {
            $result = $this->tool->deepLinkResponse(
                (string) $request->input('form_token', ''),
                array_filter((array) $request->input('course_ids', []), 'is_numeric')
            );
        } catch (LtiRequestException $e) {
            return $this->htmlError($e, 'The courses could not be added');
        }

        return $this->autopost($result['action'], $result['fields'], 'Returning to your LMS…');
    }

    /**
     * @OA\Post(
     *     path="/api/lti/tool/exchange",
     *     summary="Exchange the one-time launch code for an API token",
     *     tags={"LTI"},
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="code", type="string"))),
     *     @OA\Response(response=200, description="{token, course_id}"),
     *     @OA\Response(response=401, description="expired or used code")
     * )
     */
    public function exchange(Request $request): JsonResponse
    {
        try {
            $data = $this->tool->exchange((string) $request->input('code', ''));
        } catch (LtiRequestException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
        }

        return response()->json(['success' => true, 'data' => $data, 'message' => 'Signed in'])->header('Cache-Control', 'no-store');
    }
}
