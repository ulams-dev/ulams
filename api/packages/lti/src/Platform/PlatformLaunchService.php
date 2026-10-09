<?php

namespace Ulams\Lti\Platform;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Lti\Exceptions\LtiRequestException;
use Ulams\Lti\Models\LtiLaunch;
use Ulams\Lti\Models\LtiLineItem;
use Ulams\Lti\Models\LtiLink;
use Ulams\Lti\Models\LtiTool;
use Ulams\Lti\Services\KeyService;
use Ulams\Lti\Services\NonceStore;
use Ulams\Lti\Support\HintSigner;
use Ulams\Lti\Support\Lti;

/**
 * Platform side: we launch registered tools.
 *
 * 1. initiate*(): the front gets the tool's OIDC login URL with a signed, 2-minute login_hint
 *    (user, tool, topic or lesson). No cookies are involved, so it works inside iframes.
 * 2. authorize(): the tool sends the browser to /api/lti/platform/authorize; we check client,
 *    redirect URI and hint (single use) and post a signed id_token back to the tool.
 */
class PlatformLaunchService
{
    public const HINT = 'login_hint';
    public const DEEP_LINK_DATA = 'deep_link_data';

    public function __construct(
        private readonly KeyService $keys,
        private readonly HintSigner $hints,
        private readonly NonceStore $nonces,
        private readonly RoleMapper $roles,
    ) {
    }

    /**
     * @return array{url: string, presentation: string, tool: string}
     */
    public function initiateResourceLink(Topic $topic, Authenticatable $user): array
    {
        $link = $topic->topicable;
        if (!$link instanceof LtiLink) {
            throw new LtiRequestException('This topic is not an LTI link.', 422);
        }
        $tool = $link->tool;
        if ($tool === null || !$tool->enabled) {
            throw new LtiRequestException('This external tool is disabled.', 409);
        }

        $targetLink = $link->url ?: $tool->launch_url;

        return [
            'url' => $this->loginUrl($tool, $targetLink, [
                'type' => Lti::MSG_RESOURCE_LINK,
                'uid' => $user->getAuthIdentifier(),
                'tool' => $tool->getKey(),
                'topic' => $topic->getKey(),
            ]),
            'presentation' => $link->presentation,
            'tool' => $tool->name,
        ];
    }

    /**
     * Deep linking: the author picks content inside the tool; the tool posts the selection to
     * /api/lti/platform/deep-links and we create one topic per item in `$lesson`.
     *
     * @return array{url: string, tool: string}
     */
    public function initiateDeepLinking(LtiTool $tool, Lesson $lesson, Authenticatable $user): array
    {
        if (!$tool->enabled) {
            throw new LtiRequestException('This external tool is disabled.', 409);
        }

        return [
            'url' => $this->loginUrl($tool, $tool->deep_linking_url ?: $tool->launch_url, [
                'type' => Lti::MSG_DEEP_LINKING_REQUEST,
                'uid' => $user->getAuthIdentifier(),
                'tool' => $tool->getKey(),
                'lesson' => $lesson->getKey(),
            ]),
            'tool' => $tool->name,
        ];
    }

    /**
     * OIDC authentication request from the tool.
     *
     * @return array{action: string, fields: array<string, string>}
     * @throws LtiRequestException
     */
    public function authorize(array $params): array
    {
        if (!in_array('openid', explode(' ', (string) ($params['scope'] ?? '')), true)
            || ($params['response_type'] ?? null) !== 'id_token') {
            throw new LtiRequestException('Unsupported OIDC request (scope=openid and response_type=id_token are required).');
        }
        if (($params['response_mode'] ?? 'form_post') !== 'form_post') {
            throw new LtiRequestException('Only response_mode=form_post is supported.');
        }
        if (empty($params['nonce'])) {
            throw new LtiRequestException('Missing nonce.');
        }

        $tool = LtiTool::query()->where('client_id', (string) ($params['client_id'] ?? ''))->first();
        if ($tool === null || !$tool->enabled) {
            throw new LtiRequestException('Unknown or disabled client_id.', 401, 'unauthorized_client');
        }
        $redirectUri = (string) ($params['redirect_uri'] ?? '');
        if (!in_array($redirectUri, $tool->allowedRedirectUris(), true)) {
            throw new LtiRequestException('redirect_uri is not registered for this tool.', 400, 'invalid_request_uri');
        }

        $hint = $this->hints->verify(self::HINT, $params['login_hint'] ?? null);
        if ((int) $hint['tool'] !== $tool->getKey()) {
            throw new LtiRequestException('The login hint belongs to another tool.', 401);
        }
        if (!$this->nonces->remember(NonceStore::LOGIN_HINT, (string) $hint['jti'], (int) config('ulams_lti.login_hint_ttl', 120) + 60)) {
            throw new LtiRequestException('This launch was already used. Open the activity again.', 401);
        }

        $user = Auth::getProvider()->retrieveById($hint['uid']);
        if ($user === null) {
            throw new LtiRequestException('Unknown user.', 401);
        }

        [$claims, $topic, $course] = $hint['type'] === Lti::MSG_DEEP_LINKING_REQUEST
            ? $this->deepLinkingClaims($tool, $user, (int) $hint['lesson'])
            : $this->resourceLinkClaims($tool, $user, (int) $hint['topic']);

        $now = time();
        $claims += [
            'iss' => Lti::issuer(),
            'aud' => $tool->client_id,
            'azp' => $tool->client_id,
            'sub' => (string) $user->getAuthIdentifier(),
            'iat' => $now,
            'nbf' => $now - 5,
            'exp' => $now + (int) config('ulams_lti.id_token_ttl', 300),
            'nonce' => (string) $params['nonce'],
            Lti::CLAIM_VERSION => Lti::VERSION,
            Lti::CLAIM_DEPLOYMENT_ID => $tool->deployment_id,
            Lti::CLAIM_ROLES => $this->roles->ltiRoles($user),
            Lti::CLAIM_TOOL_PLATFORM => [
                'guid' => Lti::issuer(),
                'name' => (string) config('app.name'),
                'product_family_code' => 'ulams',
            ],
        ];
        $claims += $this->personalClaims($tool, $user);

        LtiLaunch::record([
            'direction' => 'platform',
            'message_type' => $claims[Lti::CLAIM_MESSAGE_TYPE],
            'lti_tool_id' => $tool->getKey(),
            'user_id' => $user->getAuthIdentifier(),
            'topic_id' => $topic?->getKey(),
            'course_id' => $course?->getKey(),
        ]);

        $fields = ['id_token' => $this->keys->sign($claims)];
        if (isset($params['state'])) {
            $fields['state'] = (string) $params['state'];
        }

        return ['action' => $redirectUri, 'fields' => $fields];
    }

    private function loginUrl(LtiTool $tool, string $targetLink, array $hint): string
    {
        $loginHint = $this->hints->sign(self::HINT, $hint, (int) config('ulams_lti.login_hint_ttl', 120));
        $query = http_build_query([
            'iss' => Lti::issuer(),
            'login_hint' => $loginHint,
            'lti_message_hint' => $hint['type'],
            'target_link_uri' => $targetLink,
            'client_id' => $tool->client_id,
            'lti_deployment_id' => $tool->deployment_id,
        ], '', '&', PHP_QUERY_RFC3986);

        return $tool->oidc_login_url . (str_contains($tool->oidc_login_url, '?') ? '&' : '?') . $query;
    }

    /**
     * @return array{0: array, 1: Topic, 2: Course}
     */
    private function resourceLinkClaims(LtiTool $tool, Authenticatable $user, int $topicId): array
    {
        /** @var Topic|null $topic */
        $topic = Topic::query()->with(['topicable', 'lesson.course'])->find($topicId);
        $link = $topic?->topicable;
        if (!$link instanceof LtiLink || $link->lti_tool_id !== $tool->getKey()) {
            throw new LtiRequestException('The resource link no longer exists.', 404);
        }
        $course = $topic->lesson->course;

        $lineItem = LtiLineItem::query()->firstOrCreate(
            ['lti_tool_id' => $tool->getKey(), 'course_id' => $course->getKey(), 'topic_id' => $topic->getKey()],
            ['label' => $topic->title, 'score_maximum' => $link->score_maximum ?: 100, 'resource_id' => 'topic-' . $topic->getKey()]
        );
        $lineItems = Lti::url('api/lti/platform/ags/' . $course->getKey() . '/lineitems');

        return [[
            Lti::CLAIM_MESSAGE_TYPE => Lti::MSG_RESOURCE_LINK,
            Lti::CLAIM_TARGET_LINK_URI => $link->url ?: $tool->launch_url,
            Lti::CLAIM_RESOURCE_LINK => ['id' => 'topic-' . $topic->getKey(), 'title' => $topic->title],
            Lti::CLAIM_CONTEXT => $this->context($course),
            Lti::CLAIM_LAUNCH_PRESENTATION => [
                'document_target' => $link->presentation === 'window' ? 'window' : 'iframe',
                'locale' => app()->getLocale(),
            ],
            Lti::CLAIM_CUSTOM => (object) array_merge($tool->custom ?? [], $link->custom ?? []),
            Lti::CLAIM_AGS => [
                'scope' => [Lti::SCOPE_LINEITEM, Lti::SCOPE_RESULT_READONLY, Lti::SCOPE_SCORE],
                'lineitems' => $lineItems,
                'lineitem' => $lineItems . '/' . $lineItem->getKey(),
            ],
        ], $topic, $course];
    }

    /**
     * @return array{0: array, 1: null, 2: Course}
     */
    private function deepLinkingClaims(LtiTool $tool, Authenticatable $user, int $lessonId): array
    {
        /** @var Lesson|null $lesson */
        $lesson = Lesson::query()->with('course')->find($lessonId);
        if ($lesson === null) {
            throw new LtiRequestException('The lesson no longer exists.', 404);
        }

        $data = $this->hints->sign(self::DEEP_LINK_DATA, [
            'uid' => $user->getAuthIdentifier(),
            'tool' => $tool->getKey(),
            'lesson' => $lesson->getKey(),
        ], 3600);

        return [[
            Lti::CLAIM_MESSAGE_TYPE => Lti::MSG_DEEP_LINKING_REQUEST,
            Lti::CLAIM_TARGET_LINK_URI => $tool->deep_linking_url ?: $tool->launch_url,
            Lti::CLAIM_CONTEXT => $this->context($lesson->course),
            Lti::CLAIM_DL_SETTINGS => [
                'deep_link_return_url' => Lti::url('api/lti/platform/deep-links'),
                'accept_types' => ['ltiResourceLink'],
                'accept_presentation_document_targets' => ['iframe', 'window'],
                'accept_multiple' => true,
                'auto_create' => true,
                'title' => $lesson->title,
                'data' => $data,
            ],
        ], null, $lesson->course];
    }

    private function context(Course $course): array
    {
        return [
            'id' => 'course-' . $course->getKey(),
            'title' => (string) $course->title,
            'type' => [Lti::CONTEXT_COURSE_OFFERING],
        ];
    }

    private function personalClaims(LtiTool $tool, Authenticatable $user): array
    {
        $claims = [];
        if ($tool->share_name) {
            $claims['given_name'] = (string) ($user->first_name ?? '');
            $claims['family_name'] = (string) ($user->last_name ?? '');
            $claims['name'] = trim($claims['given_name'] . ' ' . $claims['family_name']);
        }
        if ($tool->share_email && !empty($user->email)) {
            $claims['email'] = (string) $user->email;
        }

        return $claims;
    }
}
