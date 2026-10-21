<?php

namespace Ulams\Auth\Http\Controllers\Swagger;

use Ulams\Auth\Http\Requests\CompleteSocialDataRequest;
use Ulams\Auth\Http\Requests\ForgotPasswordRequest;
use Ulams\Auth\Http\Requests\RefreshTokenRequest;
use Ulams\Auth\Http\Requests\ResendVerificationEmailRequest;
use Ulams\Auth\Http\Requests\ResetPasswordRequest;
use Ulams\Auth\Http\Requests\SocialAuthRequest;
use Ulams\Auth\Http\Requests\SocialCallbackRequest;
use Ulams\Auth\Http\Requests\SocialExchangeRequest;
use Ulams\Auth\Services\Contracts\UserGroupServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

interface AuthSwagger
{
    /**
     * @OA\Post(
     *     path="/api/auth/password/forgot", operationId="auth.password.forgot.post",
     *     summary="Password forget call",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 example="user@ulams.com",
     *             ),
     *             @OA\Property(
     *                 property="return_url",
     *                 type="string",
     *                 example="https://ulams.app/password-reset",
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *      ),
     * )
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *     path="/api/auth/password/reset", operationId="auth.password.reset.post",
     *     summary="Password reset",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 example="user@ulams.com",
     *             ),
     *             @OA\Property(
     *                 property="token",
     *                 type="string",
     *                 example="hFD4UpgzTMNBeCGyfUrbvHtDb2yzSnuE",
     *             ),
     *            @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 example="zaq1@WSX",
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")
     *     )
     * )
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/auth/refresh", operationId="auth.refresh.get",
     *     summary="Refresh access token",
     *     tags={"Auth"},
     *     security={
     *          {"passport": {}},
     *      },
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *      ),
     * )
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/auth/social/{provider}", operationId="auth.social.by-provider.get",
     *     summary="Redirect to social login",
     *     description="Starts a login with Google or Facebook. `return_url` must be an origin of the tenant's own apps (otherwise 422). `challenge` is the S256 challenge of a random verifier that the caller keeps secret (base64url of its SHA-256, 43 characters). After the provider's consent the browser is sent to `return_url` with a one-time `code` (60 seconds, single use) to redeem at POST /api/auth/social/exchange together with the verifier; failures arrive as `error` (provider_denied, provider_failed, link_required, email_unverified, account_unavailable).",
     *     tags={"Auth"},
     *     @OA\Parameter(
     *          name="provider",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string",
     *              enum={"facebook", "google"},
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="return_url",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              example="https://ulams.app/auth/return",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="challenge",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              example="E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM",
     *          ),
     *      ),
     *     @OA\Response(
     *          response=302,
     *          description="Redirect to the provider",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *          ),
     *      ),
     *     @OA\Response(
     *          response=422,
     *          description="Unknown provider, return URL not allowed or challenge missing",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *      ),
     * )
     */
    public function socialRedirect(SocialAuthRequest $request): RedirectResponse|JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/auth/social/{provider}/callback", operationId="auth.social.by-provider.callback.get",
     *     summary="Redirect after successful login",
     *     description="The provider's callback. Checks the signed, single-use state and sends the browser to the return URL with a one-time `code` (and `complete=false` when the provider gave no e-mail address and the sign-up must be completed) or an `error` reason. An access token is never put in a URL.",
     *     tags={"Auth"},
     *     @OA\Parameter(
     *          name="provider",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string",
     *              enum={"facebook", "google"},
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="state",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *     @OA\Response(
     *          response=302,
     *          description="Redirect to the return URL with a code or an error",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *          ),
     *      ),
     *     @OA\Response(
     *          response=400,
     *          description="The state is missing, forged, expired or already used",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *      ),
     * )
     */
    public function socialCallback(SocialCallbackRequest $request): RedirectResponse|JsonResponse;

    /**
     * @OA\Post(
     *     path="/api/auth/social/exchange", operationId="auth.social.exchange.post",
     *     summary="Exchange a one-time social login code for a token",
     *     description="Redeems the `code` of a social login redirect together with the secret `verifier` whose S256 challenge started the login. The code works once, within 60 seconds, on the tenant that issued it. Call it from the front end's server, never from a page that may be logged. Returns the access token (`complete=true`) or, when the sign-up needs an e-mail address, `pre_user_token` for POST /api/auth/social/complete/{token} (`complete=false`).",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "verifier"},
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="verifier", type="string", description="43 to 128 characters of [A-Za-z0-9._~-]"),
     *         )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *      ),
     *     @OA\Response(
     *          response=400,
     *          description="The code is invalid, expired, already used or does not match the verifier",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *      ),
     *     @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *      ),
     * )
     */
    public function socialExchange(SocialExchangeRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *     path="/api/auth/social/complete/{token}", operationId="auth.social.complete.by-token.post",
     *     summary="Complete the missing data from socials",
     *     tags={"Auth"},
     *     @OA\Parameter(
     *          name="token",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 example="user@ulams.com",
     *             ),
     *             @OA\Property(
     *                 property="return_url",
     *                 type="string",
     *                 example="https://ulams.app/email/verify",
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *      ),
     * )
     */
    public function completeSocialData(CompleteSocialDataRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/auth/email/verify/{id}/{hash}", operationId="auth.email.verify.by-id.by-hash.get",
     *     summary="Email verification",
     *     tags={"Auth"},
     *     @OA\Parameter(
     *          name="id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="hash",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *          )
     *     )
     * )
     */
    public function verifyEmail(Request $request, string $id, string $hash);

    /**
     * @OA\Post(
     *     path="/api/auth/email/resend", operationId="auth.email.resend.post",
     *     summary="Resend email verification",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 example="user@ulams.com",
     *             ),
     *             @OA\Property(
     *                 property="return_url",
     *                 type="string",
     *                 example="https://ulams.app/email/verify",
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")
     *     )
     * )
     */
    public function resendEmailVerification(ResendVerificationEmailRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/auth/registerable-groups/", operationId="auth.registerable-groups.get",
     *     summary="List registerable groups",
     *     description="",
     *     tags={"Auth"},
     *     @OA\Response(
     *          response=200,
     *          description="successful operation, returns list of groups",
     *          @OA\JsonContent(
     *                  type="array",
     *                  @OA\Items(
     *                      ref="#/components/schemas/Group"
     *                  )
     *          )
     *     ),
     * )
     */
    public function registerableGroups(UserGroupServiceContract $userGroupService): JsonResponse;
}
