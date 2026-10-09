<?php

namespace Ulams\Lti\Platform;

use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Throwable;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Repositories\Contracts\CourseProgressRepositoryContract;
use Ulams\Lti\Exceptions\LtiRequestException;
use Ulams\Lti\Models\LtiLaunch;
use Ulams\Lti\Models\LtiLineItem;
use Ulams\Lti\Models\LtiLink;
use Ulams\Lti\Models\LtiScore;
use Ulams\Lti\Models\LtiTool;
use Ulams\Lti\Services\KeyService;
use Ulams\Lti\Services\NonceStore;
use Ulams\Lti\Support\Lti;

/**
 * Platform side of LTI Assignment and Grade Services 2.0: client-credentials tokens for tools
 * (JWT client assertion), line items per tool and course, scores (append-only) and results.
 * A completed or fully graded score completes the topic through the course progress repository.
 */
class AgsService
{
    private const CLIENT_ASSERTION_TYPE = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';

    public function __construct(
        private readonly KeyService $keys,
        private readonly ToolJwtVerifier $verifier,
        private readonly NonceStore $nonces,
        private readonly CourseProgressRepositoryContract $progress,
    ) {
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, scope: string}
     * @throws LtiRequestException
     */
    public function issueToken(array $params): array
    {
        if (($params['grant_type'] ?? null) !== 'client_credentials') {
            throw new LtiRequestException('grant_type must be client_credentials.', 400, 'unsupported_grant_type');
        }
        if (($params['client_assertion_type'] ?? null) !== self::CLIENT_ASSERTION_TYPE || empty($params['client_assertion'])) {
            throw new LtiRequestException('A JWT client assertion is required.', 400, 'invalid_client');
        }

        $assertion = (string) $params['client_assertion'];
        $unverified = ToolJwtVerifier::unverifiedClaims($assertion);
        $tool = LtiTool::query()->where('client_id', (string) ($unverified['sub'] ?? ''))->first();
        if ($tool === null || !$tool->enabled) {
            throw new LtiRequestException('Unknown client.', 401, 'invalid_client');
        }

        $claims = $this->verifier->verify($tool, $assertion);
        if (($claims['iss'] ?? null) !== $tool->client_id || ($claims['sub'] ?? null) !== $tool->client_id) {
            throw new LtiRequestException('iss and sub must be the client id.', 401, 'invalid_client');
        }
        $aud = (array) ($claims['aud'] ?? []);
        if (!in_array(Lti::url('api/lti/platform/token'), $aud, true) && !in_array(Lti::issuer(), $aud, true)) {
            throw new LtiRequestException('The assertion is not addressed to this token endpoint.', 401, 'invalid_client');
        }
        if (empty($claims['jti']) || !$this->nonces->remember(NonceStore::JTI, 'ags:' . $tool->getKey() . ':' . $claims['jti'], 3600)) {
            throw new LtiRequestException('The assertion was already used.', 401, 'invalid_client');
        }

        $requested = array_values(array_filter(explode(' ', (string) ($params['scope'] ?? ''))));
        // NRPS (member list) only when the registration allows it
        $allowed = $tool->nrps_enabled ? [...Lti::AGS_SCOPES, Lti::SCOPE_NRPS] : Lti::AGS_SCOPES;
        $scopes = array_values(array_intersect($requested, $allowed));
        if ($scopes === []) {
            throw new LtiRequestException('No supported scope requested.', 400, 'invalid_scope');
        }

        $ttl = (int) config('ulams_lti.access_token_ttl', 3600);
        $now = time();
        $token = $this->keys->sign([
            'iss' => Lti::issuer(),
            'aud' => Lti::issuer(),
            'sub' => $tool->client_id,
            'typ' => 'lti-ags',
            'tool' => $tool->getKey(),
            'scope' => implode(' ', $scopes),
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(12)),
        ]);

        return ['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => $ttl, 'scope' => implode(' ', $scopes)];
    }

    /**
     * Verifies an access token from {@see issueToken} and returns the tool and its scopes.
     *
     * @return array{0: LtiTool, 1: string[]}
     * @throws LtiRequestException
     */
    public function authenticate(?string $bearer): array
    {
        if ($bearer === null || $bearer === '') {
            throw new LtiRequestException('Missing access token.', 401, 'invalid_token');
        }
        try {
            $claims = (array) JWT::decode($bearer, $this->keys->verificationKeys());
        } catch (Throwable) {
            throw new LtiRequestException('Invalid or expired access token.', 401, 'invalid_token');
        }
        if (($claims['typ'] ?? null) !== 'lti-ags' || ($claims['iss'] ?? null) !== Lti::issuer()) {
            throw new LtiRequestException('Invalid access token.', 401, 'invalid_token');
        }
        $tool = LtiTool::query()->find((int) ($claims['tool'] ?? 0));
        if ($tool === null || !$tool->enabled || $tool->client_id !== ($claims['sub'] ?? null)) {
            throw new LtiRequestException('Invalid access token.', 401, 'invalid_token');
        }

        return [$tool, explode(' ', (string) ($claims['scope'] ?? ''))];
    }

    public static function requireScope(array $granted, string ...$any): void
    {
        if (array_intersect($granted, $any) === []) {
            throw new LtiRequestException('The access token lacks the required scope.', 403, 'insufficient_scope');
        }
    }

    /**
     * A tool may only work with courses that contain one of its links.
     */
    public function course(LtiTool $tool, int $courseId): Course
    {
        /** @var Course|null $course */
        $course = Course::query()->find($courseId);
        $linked = $course !== null && Topic::query()
            ->whereHas('lesson', fn ($q) => $q->where('course_id', $courseId))
            ->where('topicable_type', LtiLink::class)
            ->whereIn('topicable_id', LtiLink::query()->where('lti_tool_id', $tool->getKey())->select('id'))
            ->exists();
        if (!$linked) {
            throw new LtiRequestException('Unknown context.', 404, 'not_found');
        }

        return $course;
    }

    public function lineItem(LtiTool $tool, Course $course, int $id): LtiLineItem
    {
        $lineItem = LtiLineItem::query()
            ->where('lti_tool_id', $tool->getKey())
            ->where('course_id', $course->getKey())
            ->find($id);
        if ($lineItem === null) {
            throw new LtiRequestException('Unknown line item.', 404, 'not_found');
        }

        return $lineItem;
    }

    public function serializeLineItem(LtiLineItem $item): array
    {
        return array_filter([
            'id' => Lti::url('api/lti/platform/ags/' . $item->course_id . '/lineitems/' . $item->getKey()),
            'label' => $item->label,
            'scoreMaximum' => $item->score_maximum,
            'resourceId' => $item->resource_id,
            'tag' => $item->tag,
            'resourceLinkId' => $item->topic_id ? 'topic-' . $item->topic_id : null,
            'startDateTime' => $item->start_date_time?->toIso8601String(),
            'endDateTime' => $item->end_date_time?->toIso8601String(),
        ], fn ($value) => $value !== null);
    }

    public function fillLineItem(LtiLineItem $item, array $data, Course $course): LtiLineItem
    {
        if (!isset($data['label']) || !is_string($data['label']) || $data['label'] === '') {
            throw new LtiRequestException('label is required.', 400);
        }
        if (!isset($data['scoreMaximum']) || !is_numeric($data['scoreMaximum']) || $data['scoreMaximum'] <= 0) {
            throw new LtiRequestException('scoreMaximum must be a positive number.', 400);
        }
        $topicId = null;
        if (!empty($data['resourceLinkId'])) {
            $topicId = (int) preg_replace('/^topic-/', '', (string) $data['resourceLinkId']);
            $inCourse = Topic::query()->whereKey($topicId)->whereHas('lesson', fn ($q) => $q->where('course_id', $course->getKey()))->exists();
            if (!$inCourse) {
                throw new LtiRequestException('Unknown resourceLinkId.', 400);
            }
        }

        $item->fill([
            'label' => mb_substr($data['label'], 0, 255),
            'score_maximum' => (float) $data['scoreMaximum'],
            'resource_id' => isset($data['resourceId']) ? mb_substr((string) $data['resourceId'], 0, 255) : null,
            'tag' => isset($data['tag']) ? mb_substr((string) $data['tag'], 0, 255) : null,
            'start_date_time' => isset($data['startDateTime']) ? Carbon::parse($data['startDateTime']) : null,
            'end_date_time' => isset($data['endDateTime']) ? Carbon::parse($data['endDateTime']) : null,
        ]);
        if ($topicId !== null) {
            $item->topic_id = $topicId;
        }
        $item->save();

        return $item;
    }

    /**
     * Stores a score (never overwriting earlier ones) and completes the topic when the tool says
     * the activity is completed or fully graded.
     */
    public function storeScore(LtiTool $tool, LtiLineItem $item, array $data): LtiScore
    {
        $userId = (string) ($data['userId'] ?? '');
        $activity = (string) ($data['activityProgress'] ?? '');
        $grading = (string) ($data['gradingProgress'] ?? '');
        if (!in_array($activity, ['Initialized', 'Started', 'InProgress', 'Submitted', 'Completed'], true)
            || !in_array($grading, ['FullyGraded', 'Pending', 'PendingManual', 'Failed', 'NotReady'], true)) {
            throw new LtiRequestException('activityProgress and gradingProgress are required.', 400);
        }
        if (isset($data['scoreGiven']) && (!is_numeric($data['scoreGiven']) || !isset($data['scoreMaximum']) || !is_numeric($data['scoreMaximum']))) {
            throw new LtiRequestException('scoreGiven requires a numeric scoreMaximum.', 400);
        }

        // only learners this tool was launched for in this course
        $launched = $userId !== '' && ctype_digit($userId) && LtiLaunch::query()
            ->where('direction', 'platform')
            ->where('lti_tool_id', $tool->getKey())
            ->where('course_id', $item->course_id)
            ->where('user_id', (int) $userId)
            ->where('status', 'ok')
            ->exists();
        if (!$launched) {
            throw new LtiRequestException('Unknown userId for this context.', 400);
        }

        $score = LtiScore::query()->create([
            'lti_line_item_id' => $item->getKey(),
            'user_id' => (int) $userId,
            'score_given' => isset($data['scoreGiven']) ? (float) $data['scoreGiven'] : null,
            'score_maximum' => isset($data['scoreMaximum']) ? (float) $data['scoreMaximum'] : null,
            'activity_progress' => $activity,
            'grading_progress' => $grading,
            'comment' => isset($data['comment']) ? mb_substr((string) $data['comment'], 0, 4000) : null,
            'timestamp' => isset($data['timestamp']) ? Carbon::parse($data['timestamp']) : Carbon::now(),
        ]);

        if (($activity === 'Completed' || $grading === 'FullyGraded') && $item->topic_id) {
            $topic = Topic::query()->find($item->topic_id);
            $user = Auth::getProvider()->retrieveById((int) $userId);
            if ($topic !== null && $user !== null) {
                // TopicFinished (and the lesson/course checks) only fire on a transition from an
                // existing, unfinished progress row
                if (!$topic->progress()->where('user_id', $user->getAuthIdentifier())->exists()) {
                    $this->progress->updateInTopic($topic, $user, ProgressStatus::IN_PROGRESS);
                }
                $this->progress->updateInTopic($topic, $user, ProgressStatus::COMPLETE);
            }
        }

        return $score;
    }

    /**
     * Latest score per learner.
     */
    public function results(LtiLineItem $item, ?string $userId = null): array
    {
        $scores = LtiScore::query()
            ->where('lti_line_item_id', $item->getKey())
            ->when($userId !== null, fn ($q) => $q->where('user_id', (int) $userId))
            ->orderBy('id')
            ->get()
            ->keyBy('user_id');

        $lineItemUrl = $this->serializeLineItem($item)['id'];

        return $scores->values()->map(fn (LtiScore $score) => array_filter([
            'id' => $lineItemUrl . '/results/' . $score->user_id,
            'scoreOf' => $lineItemUrl,
            'userId' => (string) $score->user_id,
            'resultScore' => $score->score_given,
            'resultMaximum' => $score->score_maximum,
            'comment' => $score->comment,
        ], fn ($value) => $value !== null))->all();
    }
}
