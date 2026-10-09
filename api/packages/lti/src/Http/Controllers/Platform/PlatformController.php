<?php

namespace Ulams\Lti\Http\Controllers\Platform;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Ulams\Courses\Models\Topic;
use Ulams\Lti\Exceptions\LtiRequestException;
use Ulams\Lti\Http\Controllers\RendersLtiResponses;
use Ulams\Lti\Platform\AgsService;
use Ulams\Lti\Platform\DeepLinkingService;
use Ulams\Lti\Platform\PlatformLaunchService;

/**
 * Platform side: we launch external tools (ADR 0012).
 */
class PlatformController extends Controller
{
    use RendersLtiResponses;

    public function __construct(private readonly PlatformLaunchService $launches)
    {
    }

    /**
     * @OA\Post(
     *     path="/api/lti/launches/{topic}",
     *     summary="Start an LTI launch of an external-tool topic",
     *     description="Returns the tool's OIDC login URL (with a signed, 2-minute login_hint) to open in an iframe or a new window.",
     *     tags={"LTI"},
     *     security={{"passport": {}}},
     *     @OA\Parameter(name="topic", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="{url, presentation, tool}"),
     *     @OA\Response(response=401, description="endpoint requires authentication"),
     *     @OA\Response(response=403, description="no access to the topic"),
     *     @OA\Response(response=404, description="unknown topic"),
     *     @OA\Response(response=409, description="the tool is disabled")
     * )
     */
    public function launch(Request $request, int $topic): JsonResponse
    {
        /** @var Topic|null $model */
        $model = Topic::query()->with('topicable')->find($topic);
        if ($model === null) {
            return response()->json(['success' => false, 'message' => 'Topic not found'], 404);
        }
        if (!Gate::forUser($request->user())->allows('attend', $model)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        try {
            $data = $this->launches->initiateResourceLink($model, $request->user());
        } catch (LtiRequestException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
        }

        return response()->json(['success' => true, 'data' => $data, 'message' => 'LTI launch ready']);
    }

    /**
     * @OA\Get(
     *     path="/api/lti/platform/authorize",
     *     summary="OIDC authentication endpoint (the tool redirects the browser here)",
     *     description="Checks client_id, redirect_uri and the login_hint (single use) and posts a signed id_token to the tool. Also accepts POST.",
     *     tags={"LTI"},
     *     @OA\Response(response=200, description="auto-submitting form to the tool"),
     *     @OA\Response(response=400, description="invalid request"),
     *     @OA\Response(response=401, description="unknown client, invalid or reused login hint")
     * )
     */
    public function oidcAuthorize(Request $request): Response
    {
        try {
            $result = $this->launches->authorize($request->all());
        } catch (LtiRequestException $e) {
            return $this->htmlError($e);
        }

        return $this->autopost($result['action'], $result['fields']);
    }

    /**
     * @OA\Post(
     *     path="/api/lti/platform/token",
     *     summary="AGS access token (OAuth 2 client credentials with a JWT client assertion)",
     *     tags={"LTI"},
     *     @OA\Response(response=200, description="{access_token, token_type, expires_in, scope}"),
     *     @OA\Response(response=400, description="invalid request or scope"),
     *     @OA\Response(response=401, description="invalid client assertion")
     * )
     */
    public function token(Request $request, AgsService $ags): JsonResponse
    {
        try {
            return response()->json($ags->issueToken($request->all()))->header('Cache-Control', 'no-store');
        } catch (LtiRequestException $e) {
            return $this->oauthError($e);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/lti/platform/deep-links",
     *     summary="Deep-linking return URL (the tool posts the author's selection here)",
     *     description="Verifies the LtiDeepLinkingResponse JWT and creates one external-tool topic per ltiResourceLink item in the lesson the request was made for.",
     *     tags={"LTI"},
     *     @OA\Response(response=200, description="confirmation page; posts {type: 'ulams:lti:deep-link', topics} to the opener"),
     *     @OA\Response(response=401, description="invalid or reused response")
     * )
     */
    public function deepLinks(Request $request, DeepLinkingService $deepLinking): Response
    {
        try {
            $result = $deepLinking->handleResponse($request->input('JWT'));
        } catch (LtiRequestException $e) {
            return $this->htmlError($e, 'The content could not be added');
        }

        $count = count($result['topics']);

        return response()->view('lti::message', [
            'title' => $count === 1 ? '1 item added' : $count . ' items added',
            'message' => $result['message'] ?: 'You can close this window and continue in the course editor.',
            'postMessage' => [
                'type' => 'ulams:lti:deep-link',
                'topics' => array_map(fn (Topic $topic) => ['id' => $topic->getKey(), 'title' => $topic->title], $result['topics']),
            ],
        ]);
    }
}
