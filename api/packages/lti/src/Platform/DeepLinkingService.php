<?php

namespace Ulams\Lti\Platform;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Ulams\Courses\Http\Requests\CreateTopicAPIRequest;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Repositories\Contracts\TopicRepositoryContract;
use Ulams\Lti\Exceptions\LtiRequestException;
use Ulams\Lti\Models\LtiLaunch;
use Ulams\Lti\Models\LtiLink;
use Ulams\Lti\Models\LtiTool;
use Ulams\Lti\Services\NonceStore;
use Ulams\Lti\Support\HintSigner;
use Ulams\Lti\Support\Lti;

/**
 * Platform side: the LtiDeepLinkingResponse a tool posts back after the author picked content.
 * One LtiLink topic is created per ltiResourceLink item, through the topic repository.
 */
class DeepLinkingService
{
    public function __construct(
        private readonly ToolJwtVerifier $verifier,
        private readonly HintSigner $hints,
        private readonly NonceStore $nonces,
        private readonly TopicRepositoryContract $topics,
    ) {
    }

    /**
     * @return array{topics: Topic[], message: ?string}
     * @throws LtiRequestException
     */
    public function handleResponse(?string $jwt): array
    {
        if ($jwt === null || $jwt === '') {
            throw new LtiRequestException('Missing JWT.');
        }

        $unverified = ToolJwtVerifier::unverifiedClaims($jwt);
        $tool = LtiTool::query()->where('client_id', (string) ($unverified['iss'] ?? ''))->first();
        if ($tool === null || !$tool->enabled) {
            throw new LtiRequestException('Unknown or disabled tool.', 401);
        }

        $claims = $this->verifier->verify($tool, $jwt);
        $aud = (array) ($claims['aud'] ?? []);
        if (!in_array(Lti::issuer(), $aud, true)) {
            throw new LtiRequestException('The response is not addressed to this platform.', 401);
        }
        if (($claims[Lti::CLAIM_MESSAGE_TYPE] ?? null) !== Lti::MSG_DEEP_LINKING_RESPONSE
            || ($claims[Lti::CLAIM_VERSION] ?? null) !== Lti::VERSION) {
            throw new LtiRequestException('Not an LTI 1.3 deep-linking response.');
        }
        if (($claims[Lti::CLAIM_DEPLOYMENT_ID] ?? null) !== $tool->deployment_id) {
            throw new LtiRequestException('Unknown deployment.', 401);
        }
        if (empty($claims['nonce']) || !$this->nonces->remember(NonceStore::JTI, 'dl:' . $tool->getKey() . ':' . $claims['nonce'], 3600)) {
            throw new LtiRequestException('This response was already used.', 401);
        }

        $data = $this->hints->verify(PlatformLaunchService::DEEP_LINK_DATA, $claims[Lti::CLAIM_DL_DATA] ?? null);
        if ((int) $data['tool'] !== $tool->getKey()) {
            throw new LtiRequestException('The response belongs to another tool.', 401);
        }
        /** @var Lesson|null $lesson */
        $lesson = Lesson::query()->find((int) $data['lesson']);
        if ($lesson === null) {
            throw new LtiRequestException('The lesson no longer exists.', 404);
        }

        $items = array_values(array_filter(
            (array) ($claims[Lti::CLAIM_DL_CONTENT_ITEMS] ?? []),
            fn ($item) => is_array($item) && ($item['type'] ?? null) === 'ltiResourceLink'
        ));

        $topics = DB::transaction(function () use ($items, $tool, $lesson, $data) {
            $user = Auth::getProvider()->retrieveById($data['uid']);
            if ($user !== null) {
                Auth::setUser($user);
            }
            $order = (int) Topic::query()->where('lesson_id', $lesson->getKey())->max('order');

            return array_map(fn (array $item) => $this->createTopic($tool, $lesson, $item, ++$order), $items);
        });

        LtiLaunch::record([
            'direction' => 'platform',
            'message_type' => Lti::MSG_DEEP_LINKING_RESPONSE,
            'lti_tool_id' => $tool->getKey(),
            'user_id' => $data['uid'],
            'course_id' => $lesson->course_id,
        ]);

        return ['topics' => $topics, 'message' => $claims[Lti::CLAIM_DL_MSG] ?? null];
    }

    private function createTopic(LtiTool $tool, Lesson $lesson, array $item, int $order): Topic
    {
        $custom = array_filter((array) ($item['custom'] ?? []), fn ($value) => is_scalar($value));
        $input = array_filter([
            'title' => mb_substr((string) ($item['title'] ?? $tool->name), 0, 255) ?: $tool->name,
            'lesson_id' => $lesson->getKey(),
            'order' => $order,
            'active' => true,
            'summary' => isset($item['text']) ? (string) $item['text'] : null,
            'topicable_type' => LtiLink::class,
            'lti_tool_id' => $tool->getKey(),
            'url' => $item['url'] ?? null,
            'custom' => $custom === [] ? null : array_map('strval', $custom),
            'presentation' => (($item['window'] ?? null) !== null && ($item['iframe'] ?? null) === null) ? 'window' : 'iframe',
            'score_maximum' => isset($item['lineItem']['scoreMaximum']) ? (float) $item['lineItem']['scoreMaximum'] : null,
        ], fn ($value) => $value !== null);

        $request = new CreateTopicAPIRequest($input);
        $request->setValidator(Validator::make($input, $request->rules()));
        $validator = Validator::make($input, LtiLink::rules());
        if ($validator->fails()) {
            throw new LtiRequestException('Invalid content item: ' . $validator->errors()->first(), 422);
        }

        return $this->topics->createFromRequest($request);
    }
}
