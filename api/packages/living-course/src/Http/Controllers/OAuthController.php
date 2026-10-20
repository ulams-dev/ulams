<?php

namespace Ulams\LivingCourse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ulams\LivingCourse\Connectors\ConnectorException;
use Ulams\LivingCourse\Http\Controllers\Concerns\ResolvesLivingCourse;
use Ulams\LivingCourse\Oauth\OAuthService;

/**
 * "Connect your Google or Notion account" for the Drive and Notion connectors. The start needs the same right
 * as connecting a source; the callback is the vendor's redirect and is authorised by the encrypted,
 * expiring state of the start. The tokens never reach the browser: it only gets a one-time grant id that
 * the connect request redeems.
 *
 * @OA\Post(path="/api/admin/living-course/oauth/{provider}/start", summary="The vendor sign-in address for Connect your account (provider google or notion); the session must be one the caller may change", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="provider", in="path", required=true, @OA\Schema(type="string", enum={"google","notion"})),
 *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="sessionId", type="string"))),
 *     @OA\Response(response=200, description="url to send the browser to"), @OA\Response(response=403, description="another author's session"), @OA\Response(response=422, description="sign-in not set up on this installation"))
 * @OA\Get(path="/api/living-course/oauth/{provider}/callback", summary="The vendor's redirect after the sign-in: redirects to the studio with a one-time grant, or answers with it", tags={"Living Course"},
 *     @OA\Parameter(name="provider", in="path", required=true, @OA\Schema(type="string", enum={"google","notion"})),
 *     @OA\Parameter(name="code", in="query", @OA\Schema(type="string")), @OA\Parameter(name="state", in="query", @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="grant (when no return address is configured)"), @OA\Response(response=302, description="redirect to the studio"), @OA\Response(response=422, description="sign-in refused or expired"))
 */
class OAuthController extends Controller
{
    use ResolvesLivingCourse;

    public function __construct(private readonly OAuthService $oauth)
    {
    }

    public function start(Request $request, string $provider): JsonResponse
    {
        $data = $request->validate(['sessionId' => ['required', 'string']]);
        $this->sessionFor($request, $data['sessionId'], 'act');
        try {
            $url = $this->oauth->start($provider, (int) $request->user()->getKey());
        } catch (ConnectorException $e) {
            return self::fail($e->getMessage(), 422);
        }

        return self::ok(['url' => $url]);
    }

    public function callback(Request $request, string $provider): JsonResponse|RedirectResponse
    {
        $return = (string) config('living_course.oauth.return_url', '');
        try {
            if ($request->filled('error')) {
                throw new ConnectorException('The sign-in was cancelled or refused. Start again.');
            }
            $grant = $this->oauth->complete($provider, (string) $request->query('code', ''), (string) $request->query('state', ''));
        } catch (ConnectorException $e) {
            // a refusal says what to do next and never repeats what the vendor sent
            return $return !== ''
                ? redirect()->away($return . (str_contains($return, '?') ? '&' : '?') . http_build_query(['provider' => $provider, 'oauth_error' => $e->getMessage()]))
                : self::fail($e->getMessage(), 422);
        }

        return $return !== ''
            ? redirect()->away($return . (str_contains($return, '?') ? '&' : '?') . http_build_query(['provider' => $provider, 'grant' => $grant]))
            : self::ok(['grant' => $grant, 'provider' => $provider]);
    }
}
