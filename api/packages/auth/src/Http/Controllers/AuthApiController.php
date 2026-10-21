<?php

namespace Ulams\Auth\Http\Controllers;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Events\AccountConfirmed;
use Ulams\Auth\Events\AccountRegistered;
use Ulams\Auth\Exceptions\AuthException;
use Ulams\Auth\Http\Controllers\Swagger\AuthSwagger;
use Ulams\Auth\Http\Requests\CompleteSocialDataRequest;
use Ulams\Auth\Http\Requests\ForgotPasswordRequest;
use Ulams\Auth\Http\Requests\RefreshTokenRequest;
use Ulams\Auth\Http\Requests\ResendVerificationEmailRequest;
use Ulams\Auth\Http\Requests\ResetPasswordRequest;
use Ulams\Auth\Http\Requests\SocialAuthRequest;
use Ulams\Auth\Http\Requests\SocialCallbackRequest;
use Ulams\Auth\Http\Requests\SocialExchangeRequest;
use Ulams\Auth\Exceptions\InvalidReturnUrlException;
use Ulams\Auth\Exceptions\LoginHandoffException;
use Ulams\Auth\Http\Resources\LoginResource;
use Ulams\Auth\Http\Resources\UserGroupResource;
use Ulams\Auth\Repositories\Contracts\UserRepositoryContract;
use Ulams\Auth\Services\Contracts\AuthServiceContract;
use Ulams\Auth\Services\Contracts\SocialAccountServiceContract;
use Ulams\Auth\Services\Contracts\UserGroupServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;

class AuthApiController extends UlamsBaseController implements AuthSwagger
{
    private AuthServiceContract $authService;
    private UserRepositoryContract $userRepository;
    private SocialAccountServiceContract $socialAccountService;

    /**
     * @param AuthServiceContract $authService
     */
    public function __construct(
        AuthServiceContract $authService,
        UserRepositoryContract $userRepository,
        SocialAccountServiceContract $socialAccountService
    )
    {
        $this->authService = $authService;
        $this->userRepository = $userRepository;
        $this->socialAccountService = $socialAccountService;
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword(
            $request->input('email'),
            $request->input('return_url'),
        );

        return $this->sendSuccess(__('Password reset email sent'));
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->resetPassword(
                $request->input('email'),
                $request->input('token'),
                $request->input('password'),
            );

            return $this->sendSuccess(__('New password saved'));
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), 401);
        }
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $token = $this->authService->refreshToken($request->user());

        return $this->sendResponse(LoginResource::make($token), __('Token refreshed'));
    }

    public function socialRedirect(SocialAuthRequest $request): RedirectResponse|JsonResponse
    {
        $provider = $request->route('provider');

        try {
            $state = $this->socialAccountService->startLogin(
                $provider,
                $request->getHost(),
                $request->input('return_url'),
                $request->input('challenge')
            );
        } catch (InvalidReturnUrlException $e) {
            return $this->sendError($e->getMessage(), 422);
        }

        /** @var \Laravel\Socialite\SocialiteManager|\Laravel\Socialite\Two\AbstractProvider $socialite */
        $socialite = Socialite::driver($provider);

        // stateless: the state is ours (signed, single use, kept server-side), not the session's
        return $socialite
            ->stateless()
            ->with(['state' => $state])
            ->redirect();
    }

    public function socialCallback(SocialCallbackRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $url = $this->socialAccountService->handleCallback(
                $request->route('provider'),
                $request->input('state'),
                $request->getHost(),
                $request->filled('error') ? (string) $request->input('error') : null
            );
        } catch (LoginHandoffException $e) {
            // without a valid state there is no trusted place to send the browser to
            return $this->sendError(__('The login attempt is invalid or has expired. Start again.'), 400);
        }

        return redirect()->away($url)->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function socialExchange(SocialExchangeRequest $request): JsonResponse
    {
        try {
            $result = $this->socialAccountService->exchange(
                $request->input('code'),
                $request->input('verifier'),
                $request->getHost()
            );
        } catch (LoginHandoffException $e) {
            return $this->sendError(__('The login code is invalid or has expired. Start again.'), 400);
        }

        if (!$result['complete']) {
            return $this->sendResponse(
                ['complete' => false, 'pre_user_token' => $result['pre_user_token']],
                __('More details are needed to finish signing up')
            );
        }

        return $this->sendResponse(
            ['complete' => true] + LoginResource::make($result['token'])->toArray($request),
            __('Login successful')
        )->withHeaders(['Cache-Control' => 'no-store']);
    }

    public function completeSocialData(CompleteSocialDataRequest $request): JsonResponse
    {
        try {
            $this->socialAccountService->completeData(
                $request->input('token'),
                $request->input('email'),
                $request->input('return_url')
            );
        } catch (AuthException $e) {
            return $this->sendError($e->getMessage(), $e->getCode());
        }

        return $this->sendSuccess(__('The data has been completed. Verification email has been sent.'));
    }

    public function verifyEmail(Request $request, string $id, string $hash)
    {
        $user = $this->userRepository->find($id);

        if (
            $user instanceof MustVerifyEmail &&
            hash_equals($id, (string)$user->getKey()) &&
            hash_equals($hash, sha1($user->getEmailForVerification())) &&
            !$user->hasVerifiedEmail()
        ) {
            $user->markEmailAsVerified();
            event(new AccountConfirmed($user));
        }

        if ($request->wantsJson()) {
            return $this->sendSuccess(__('Your email address was successfully verified'));
        }

        return view('auth::email-verified');
    }

    public function resendEmailVerification(ResendVerificationEmailRequest $request): JsonResponse
    {
        $user = $this->userRepository->findByEmail($request->input('email'));

        if ($user instanceof MustVerifyEmail && !$user->hasVerifiedEmail()) {
            event(new AccountRegistered($user, $request->input('return_url', Config::get(UlamsAuthServiceProvider::CONFIG_KEY . '.return_url'))));
        }

        return $this->sendSuccess(__('Verification message resent if email exists in database'));
    }

    public function registerableGroups(UserGroupServiceContract $userGroupService): JsonResponse
    {
        return $this->sendResponseForResource(UserGroupResource::collection($userGroupService->getRegisterableGroups()), __('Registerable groups list'));
    }
}
