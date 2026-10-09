<?php

namespace Ulams\Lti\Tool;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Packback\Lti1p3\DeepLinkResources\Resource;
use Packback\Lti1p3\Factories\MessageFactory;
use Packback\Lti1p3\LtiDeepLink;
use Packback\Lti1p3\LtiException;
use Packback\Lti1p3\LtiLineitem;
use Packback\Lti1p3\LtiOidcLogin;
use Packback\Lti1p3\LtiServiceConnector;
use Packback\Lti1p3\Messages\DeepLinkingRequest;
use Packback\Lti1p3\Messages\ResourceLinkRequest;
use Packback\Lti1p3\OidcException;
use Throwable;
use Ulams\CourseAccess\Models\Course as AccessCourse;
use Ulams\CourseAccess\Services\Contracts\CourseAccessServiceContract;
use Ulams\Courses\Models\Course;
use Ulams\Lti\Exceptions\LtiRequestException;
use Ulams\Lti\Models\LtiGradeTarget;
use Ulams\Lti\Models\LtiLaunch;
use Ulams\Lti\Models\LtiPlatform;
use Ulams\Lti\Models\LtiUserLink;
use Ulams\Lti\Platform\RoleMapper;
use Ulams\Lti\Services\NonceStore;
use Ulams\Lti\Support\HintSigner;
use Ulams\Lti\Support\Lti;
use Ulams\Lti\Support\SafeHttp;

/**
 * Tool side: Moodle, Canvas and other platforms launch our courses. Launch validation (state,
 * nonce, signature, deployment, claims) is done by packbackbooks/lti-1p3-tool; this service maps
 * the platform user to a ulams user, grants course access and hands the front a one-time code.
 */
class ToolLaunchService
{
    public const DEEP_LINK_FORM = 'tool_deep_link';

    private ?Client $http = null;

    public function __construct(
        private readonly ToolDatabase $db,
        private readonly ToolCache $cache,
        private readonly ServerSideState $state,
        private readonly NonceStore $nonces,
        private readonly RoleMapper $roles,
        private readonly HintSigner $hints,
        private readonly CourseAccessServiceContract $access,
    ) {
    }

    /** Tests replace the HTTP client (platform JWKS, token and AGS requests). */
    public function setHttpClient(Client $client): void
    {
        $this->http = $client;
    }

    public function connector(): LtiServiceConnector
    {
        return new LtiServiceConnector($this->cache, $this->http ?? SafeHttp::client());
    }

    /**
     * OIDC third-party initiated login: returns the platform's auth URL to redirect to.
     */
    public function loginRedirect(array $request): string
    {
        try {
            return LtiOidcLogin::new($this->db, $this->cache, $this->state)
                ->getRedirectUrl(Lti::url('api/lti/tool/launch'), $request);
        } catch (OidcException $e) {
            throw new LtiRequestException($e->getMessage(), 400);
        }
    }

    /**
     * @return array{type: string, redirect?: string, form_token?: string, courses?: array, user_id: int}
     */
    public function launch(array $request): array
    {
        try {
            $message = (new MessageFactory($this->db, $this->connector(), $this->cache, $this->state))->create($request);
        } catch (LtiException | \UnexpectedValueException | \DomainException $e) {
            // LtiException: validation; UnexpectedValue/Domain: php-jwt signature, expiry, key errors
            $this->recordFailure($request, $e->getMessage());
            throw new LtiRequestException($e->getMessage(), 401);
        }

        $body = $message->getBody();
        $platform = $this->db->platform((string) $body['iss'], $message->getAud());
        if ($platform === null) {
            throw new LtiRequestException('Unknown platform.', 401);
        }

        $user = DB::transaction(fn () => $this->user($platform, $body));

        if ($message instanceof DeepLinkingRequest) {
            if ($this->roles->ulamsRole((array) ($body[Lti::CLAIM_ROLES] ?? [])) !== 'tutor') {
                throw new LtiRequestException('Only instructors can add course links.', 403);
            }
            $this->record($platform, $user->getKey(), Lti::MSG_DEEP_LINKING_REQUEST, null);

            return [
                'type' => 'deep_link',
                'user_id' => $user->getKey(),
                'form_token' => $this->hints->sign(self::DEEP_LINK_FORM, [
                    'launch' => $message->getLaunchId(),
                    'platform' => $platform->getKey(),
                    'uid' => $user->getKey(),
                ], 1800),
                'courses' => Course::query()
                    ->where('status', 'published')
                    ->orderBy('title')
                    ->limit(500)
                    ->get(['id', 'title'])
                    ->map(fn (Course $course) => ['id' => $course->getKey(), 'title' => $course->title])
                    ->all(),
            ];
        }

        if (!$message instanceof ResourceLinkRequest) {
            throw new LtiRequestException('Unsupported message type.', 400);
        }

        $course = $this->course($platform, $body);
        $this->access->addAccessForUsers(AccessCourse::query()->findOrFail($course->getKey()), [$user->getKey()]);
        $this->rememberGradeTarget($platform, $user->getKey(), $course->getKey(), $body);
        $this->record($platform, $user->getKey(), Lti::MSG_RESOURCE_LINK, $course->getKey());

        $code = Str::random(48);
        $this->nonces->remember(NonceStore::CODE, $code, (int) config('ulams_lti.code_ttl', 60), [
            'uid' => $user->getKey(),
            'course' => $course->getKey(),
        ]);

        return [
            'type' => 'resource',
            'user_id' => $user->getKey(),
            'redirect' => strtr((string) config('ulams_lti.tool_landing_url'), [
                '{front}' => rtrim((string) config('app.frontend_url'), '/'),
                '{code}' => $code,
                '{course}' => (string) $course->getKey(),
            ]),
        ];
    }

    /**
     * Builds the LtiDeepLinkingResponse for the courses the instructor picked.
     *
     * @param int[] $courseIds
     * @return array{action: string, fields: array<string, string>}
     */
    public function deepLinkResponse(string $formToken, array $courseIds): array
    {
        $form = $this->hints->verify(self::DEEP_LINK_FORM, $formToken);
        $body = $this->cache->getLaunchData((string) $form['launch']);
        if ($body === null || ($body[Lti::CLAIM_MESSAGE_TYPE] ?? null) !== Lti::MSG_DEEP_LINKING_REQUEST) {
            throw new LtiRequestException('The deep-linking session has expired. Start again from your LMS.', 410);
        }
        /** @var LtiPlatform|null $platform */
        $platform = LtiPlatform::query()->find((int) $form['platform']);
        if ($platform === null || !$platform->enabled) {
            throw new LtiRequestException('Unknown platform.', 401);
        }

        $courses = Course::query()->whereIn('id', array_map('intval', $courseIds))->get();
        if ($courses->isEmpty()) {
            throw new LtiRequestException('Pick at least one course.', 422);
        }

        $settings = (array) $body[Lti::CLAIM_DL_SETTINGS];
        $deepLink = new LtiDeepLink($this->db->registration($platform), (string) $body[Lti::CLAIM_DEPLOYMENT_ID], $settings);
        $resources = $courses->map(fn (Course $course) => Resource::new()
            ->setTitle((string) $course->title)
            ->setText((string) ($course->summary ?? ''))
            ->setUrl(Lti::url('api/lti/tool/launch'))
            ->setCustomParams(['course_id' => (string) $course->getKey()])
            ->setLineItem(LtiLineitem::new()->setScoreMaximum(100)->setLabel((string) $course->title)))
            ->all();

        $this->nonces->take(NonceStore::LAUNCH, (string) $form['launch']);

        return ['action' => $deepLink->returnUrl(), 'fields' => ['JWT' => $deepLink->getResponseJwt($resources)]];
    }

    /**
     * Exchanges the one-time code from the landing URL for an API token.
     *
     * @return array{token: string, course_id: int}
     */
    public function exchange(string $code): array
    {
        $payload = $this->nonces->take(NonceStore::CODE, $code);
        if ($payload === null) {
            throw new LtiRequestException('This sign-in link has expired. Open the activity in your LMS again.', 401);
        }
        $user = Auth::getProvider()->retrieveById((int) $payload['uid']);
        if ($user === null) {
            throw new LtiRequestException('Unknown user.', 401);
        }

        return [
            'token' => $user->createToken('LTI launch')->accessToken,
            'course_id' => (int) $payload['course'],
        ];
    }

    private function user(LtiPlatform $platform, array $body)
    {
        $model = config('auth.providers.users.model');
        $sub = (string) $body['sub'];

        $link = LtiUserLink::query()->where('lti_platform_id', $platform->getKey())->where('sub', $sub)->first();
        if ($link !== null && ($user = $model::query()->find($link->user_id)) !== null) {
            return $user;
        }

        // Never link to an existing account by e-mail: the platform asserts the address, and
        // trusting it would let any registered platform sign in as any local user.
        $email = filter_var($body['email'] ?? null, FILTER_VALIDATE_EMAIL) ?: null;
        if ($email === null || $model::query()->where('email', $email)->exists()) {
            $email = sprintf('lti-%d-%s@lti.invalid', $platform->getKey(), substr(hash('sha256', $sub), 0, 20));
        }

        $user = $model::query()->create([
            'first_name' => mb_substr((string) ($body['given_name'] ?? $body['name'] ?? 'LTI'), 0, 255) ?: 'LTI',
            'last_name' => mb_substr((string) ($body['family_name'] ?? 'Learner'), 0, 255) ?: 'Learner',
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->guard_name = 'api';
        $user->assignRole($this->roles->ulamsRole((array) ($body[Lti::CLAIM_ROLES] ?? [])));

        LtiUserLink::query()->create(['lti_platform_id' => $platform->getKey(), 'sub' => $sub, 'user_id' => $user->getKey()]);

        return $user;
    }

    private function course(LtiPlatform $platform, array $body): Course
    {
        $custom = (array) ($body[Lti::CLAIM_CUSTOM] ?? []);
        $courseId = $custom['course_id'] ?? null;
        if ($courseId === null) {
            parse_str((string) parse_url((string) ($body[Lti::CLAIM_TARGET_LINK_URI] ?? ''), PHP_URL_QUERY), $query);
            $courseId = $query['course'] ?? $platform->default_course_id;
        }

        /** @var Course|null $course */
        $course = $courseId !== null && ctype_digit((string) $courseId) ? Course::query()->find((int) $courseId) : null;
        if ($course === null) {
            throw new LtiRequestException('This link does not point to a course. Ask the instructor to pick a course again.', 404);
        }

        return $course;
    }

    private function rememberGradeTarget(LtiPlatform $platform, int $userId, int $courseId, array $body): void
    {
        $ags = $body[Lti::CLAIM_AGS] ?? null;
        if (!is_array($ags) || !in_array(Lti::SCOPE_SCORE, (array) ($ags['scope'] ?? []), true)) {
            return;
        }
        if (empty($ags['lineitem']) && empty($ags['lineitems'])) {
            return;
        }

        LtiGradeTarget::query()->updateOrCreate(
            ['lti_platform_id' => $platform->getKey(), 'user_id' => $userId, 'course_id' => $courseId],
            [
                'sub' => (string) $body['sub'],
                'lineitem' => $ags['lineitem'] ?? null,
                'lineitems' => $ags['lineitems'] ?? null,
                'scopes' => array_values((array) $ags['scope']),
            ]
        );
    }

    private function record(LtiPlatform $platform, int $userId, string $type, ?int $courseId): void
    {
        LtiLaunch::record([
            'direction' => 'tool',
            'message_type' => $type,
            'lti_platform_id' => $platform->getKey(),
            'user_id' => $userId,
            'course_id' => $courseId,
        ]);
    }

    private function recordFailure(array $request, string $error): void
    {
        try {
            LtiLaunch::record([
                'direction' => 'tool',
                'message_type' => 'unknown',
                'status' => 'failed',
                'error' => mb_substr($error, 0, 1000),
            ]);
        } catch (Throwable) {
            // auditing must not hide the original error
        }
    }
}
