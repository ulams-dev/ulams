<?php

namespace Ulams\Lrs\Services;

use Ulams\Courses\Models\Topic;
use Ulams\Lrs\Services\Contracts\LrsServiceContract;
use Ulams\Lrs\Services\Contracts\XapiDocumentServiceContract;
use Ulams\Lrs\Xapi\DocumentScope;
use Illuminate\Http\Request;
use Ulams\Lrs\Models\Access;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LrsService implements LrsServiceContract
{
    public function __construct(
        private readonly LaunchTokenService $launchTokens,
        private readonly XapiDocumentServiceContract $documents,
    ) {
    }

    public function launchParams(?int $courseId = null, ?int $topicId = null, ?int $auId = null): array
    {
        $access = Access::firstOrFail();
        $user = Auth::user();

        if ($topicId) {
            $topic = Topic::findOrFail($topicId);
            $courseId = $topic->lesson->course->getKey();
        }

        // The AU gets a one-time launch token, never the learner's access token (ADR 0046).
        $registration = (string) Str::uuid();
        $oneTime = $this->launchTokens->issue((int) $user?->getKey(), $registration, $auId, $access);
        $fetch = route("cmi5.fetch") . "?token=" . $oneTime;

        $result = [
            'endpoint' => $access->xapi_endpoint,
            'fetch' => $fetch,
            'actor' => [
                'objectType' => 'Agent',
                'account' => [
                    'homePage' => "https://ulams.app",
                    'name' => isset($user) ? $user->email : '',
                ]
            ],
            'registration' => $registration,
            'activityId' => $this->getActivityId($courseId, $topicId)
        ];

        $url = http_build_query([
            'endpoint' => $result['endpoint'],
            'fetch' => $result['fetch'],
            'actor' => json_encode($result['actor']),
            'registration' => $result['registration'],
            'activityId' => $result['activityId'],
        ]);

        $result['url'] = $url;
        $result['state'] = [
            'stateId' => 'LMS.LaunchData',
            'agent' => json_encode($result['actor']),
            'activityId' => $result['activityId'],
            'registration' => $result['registration'],
        ];

        return $result;
    }

    public function saveState(array $params): array
    {
        $scope = DocumentScope::state(Request::create('/', 'GET', $params['state']));
        // `contextTemplate` is the context every AU statement starts from (cmi5 specification 9.6.2.1),
        // not a statement wrapper
        $launchData = [
            'contextTemplate' => [
                'registration' => $params['registration'],
                'contextActivities' => ['grouping' => [['objectType' => 'Activity', 'id' => $params['activityId']]]],
                'extensions' => ['https://w3id.org/xapi/cmi5/context/extensions/sessionid' => (string) Str::uuid()],
            ],
            'launchMode' => 'Normal',
            'moveOn' => 'CompletedOrPassed',
        ];

        $this->documents->save($scope, Access::firstOrFail(), (string) json_encode($launchData), 'application/json', false);

        return $params;
    }

    public function saveAgent(array $params): array
    {
        $scope = DocumentScope::agentProfile(Request::create('/', 'GET', [
            'agent' => json_encode($params['actor']),
            'profileId' => 'cmi5LearnerPreferences',
        ]));

        $this->documents->save($scope, Access::firstOrFail(), '{}', 'application/json', true);

        return $params;
    }

    private function getActivityId(?int $courseId = null, ?int $topicId = null): string
    {
        if ($courseId && $topicId) {
            return url("xapi/activities/course/{$courseId}/topic/{$topicId}");
        }
        elseif ($courseId) {
            return url("xapi/activities/course/{$courseId}");
        }

        return url("xapi/activities/preview");
    }
}
