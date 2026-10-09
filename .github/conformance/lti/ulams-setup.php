<?php

/*
 * Conformance fixture for the Moodle LTI round trip (nightly-conformance.yml), run from api/:
 *
 *   php ../.github/conformance/lti/ulams-setup.php seed
 *       -> {"course_id": …, "endpoints": {…}}   a published course with one topic, LTI keys
 *   php ../.github/conformance/lti/ulams-setup.php platform '<moodle-setup.php JSON>'
 *       -> {"platform_id": …}                  registers Moodle as a platform that may launch us
 *   php ../.github/conformance/lti/ulams-setup.php grade-target <course id>
 *       -> {"sent": true|false, "error": …}     the grade target written by the launch
 *   php ../.github/conformance/lti/ulams-setup.php tool <course id> '<moodle-setup.php tool-draft JSON>'
 *       -> {"client_id", "deployment_id", "topic_id", "learner_token", "platform": {…}}
 *          registers Moodle as a tool, adds an LTI topic launching its published course and an
 *          enrolled learner with a Passport token
 *   php ../.github/conformance/lti/ulams-setup.php scores <topic id>
 *       -> {"scores": [{score_given, score_maximum, activity_progress, grading_progress}]}
 *   php ../.github/conformance/lti/ulams-setup.php launches
 *       -> {"ok": <count>, "failed": [<errors>]}  tool-side launch audit (saLTIre job)
 *
 * Test fixtures only: the conformance database is thrown away after the run.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Lti\Models\LtiGradeTarget;
use Ulams\Lti\Models\LtiLineItem;
use Ulams\Lti\Models\LtiLink;
use Ulams\Lti\Models\LtiPlatform;
use Ulams\Lti\Models\LtiScore;
use Ulams\Lti\Models\LtiTool;
use Ulams\Lti\Support\Lti;
use Ulams\TopicTypes\Models\TopicContent\RichText;

require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$out = static function (array $data): void {
    fwrite(STDOUT, json_encode($data, JSON_UNESCAPED_SLASHES) . "\n");
};

switch ($argv[1] ?? '') {
    case 'seed':
        Artisan::call('ulams:lti:rotate-keys', ['--init' => true]);
        $course = Course::query()->create(['title' => 'LTI conformance course', 'status' => 'published']);
        $lesson = Lesson::query()->create(['title' => 'Lesson', 'course_id' => $course->getKey(), 'active' => true]);
        $content = RichText::query()->create(['value' => 'Hello from ulams.']);
        $topic = new Topic(['title' => 'Read this', 'lesson_id' => $lesson->getKey(), 'active' => true]);
        $topic->topicable()->associate($content);
        $topic->save();
        $out([
            'course_id' => $course->getKey(),
            'topic_id' => $topic->getKey(),
            'endpoints' => [
                'issuer' => Lti::issuer(),
                'jwks_url' => Lti::url('api/lti/jwks'),
                'oidc_login_url' => Lti::url('api/lti/tool/login'),
                'launch_url' => Lti::url('api/lti/tool/launch'),
            ],
        ]);
        break;

    case 'platform':
        $moodle = json_decode($argv[2] ?? '', true, 512, JSON_THROW_ON_ERROR);
        $platform = LtiPlatform::query()->create([
            'name' => 'Moodle (conformance)',
            'issuer' => $moodle['issuer'],
            'client_id' => $moodle['client_id'],
            'deployment_ids' => [(string) $moodle['deployment_id']],
            'auth_login_url' => $moodle['auth_login_url'],
            'auth_token_url' => $moodle['auth_token_url'],
            'jwks_url' => $moodle['jwks_url'],
            'enabled' => true,
        ]);
        $out(['platform_id' => $platform->getKey()]);
        break;

    case 'grade-target':
        $target = LtiGradeTarget::query()->where('course_id', (int) ($argv[2] ?? 0))->latest('id')->first();
        $out([
            'found' => $target !== null,
            'sent' => $target?->last_sent_at !== null,
            'error' => $target?->last_error,
            'lineitem' => $target?->lineitem,
        ]);
        break;

    case 'tool':
        $course = Course::query()->findOrFail((int) ($argv[2] ?? 0));
        $moodle = json_decode($argv[3] ?? '', true, 512, JSON_THROW_ON_ERROR);
        $tool = LtiTool::query()->create([
            'name' => 'Moodle (conformance)',
            'oidc_login_url' => $moodle['login_url'],
            'launch_url' => $moodle['launch_url'],
            'deep_linking_url' => $moodle['deep_linking_url'] ?? null,
            'redirect_uris' => [$moodle['launch_url'], $moodle['deep_linking_url'] ?? $moodle['launch_url']],
            'jwks_url' => $moodle['jwks_url'],
            'share_name' => true,
            'share_email' => true,
            'enabled' => true,
        ]);
        $lesson = Lesson::query()->create(['title' => 'From Moodle', 'course_id' => $course->getKey(), 'active' => true]);
        $link = LtiLink::query()->create([
            'lti_tool_id' => $tool->getKey(),
            'url' => $moodle['launch_url'],
            // Moodle finds the published resource by its uuid in the custom parameter `id`
            'custom' => ['id' => $moodle['resource_uuid']],
            'presentation' => 'window',
            'score_maximum' => 100,
        ]);
        $topic = new Topic(['title' => 'Moodle activity', 'lesson_id' => $lesson->getKey(), 'active' => true]);
        $topic->topicable()->associate($link);
        $topic->save();

        $userModel = config('auth.providers.users.model');
        $learner = $userModel::query()->create([
            'first_name' => 'Lea',
            'last_name' => 'Learner',
            'email' => 'learner-' . bin2hex(random_bytes(3)) . '@ulams.test',
            'password' => bcrypt(bin2hex(random_bytes(16))),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $learner->assignRole('student');
        $course->users()->syncWithoutDetaching([$learner->getKey()]);

        $out([
            'client_id' => $tool->client_id,
            'deployment_id' => $tool->deployment_id,
            'topic_id' => $topic->getKey(),
            'learner_email' => $learner->email,
            'learner_token' => $learner->createToken('conformance')->accessToken,
            'platform' => [
                'issuer' => Lti::issuer(),
                'oidc_auth_url' => Lti::url('api/lti/platform/authorize'),
                'token_url' => Lti::url('api/lti/platform/token'),
                'jwks_url' => Lti::url('api/lti/jwks'),
            ],
        ]);
        break;

    case 'scores':
        $topicId = (int) ($argv[2] ?? 0);
        $items = LtiLineItem::query()->where('topic_id', $topicId)->pluck('id');
        $out(['line_items' => $items->count(), 'scores' => LtiScore::query()->whereIn('lti_line_item_id', $items)->get()
            ->map(fn (LtiScore $s) => [
                'score_given' => $s->score_given,
                'score_maximum' => $s->score_maximum,
                'activity_progress' => $s->activity_progress,
                'grading_progress' => $s->grading_progress,
            ])->all()]);
        break;

    case 'launches':
        // tool-side launch audit (saLTIre job): successful count and the errors of rejected ones
        $out([
            'ok' => \Ulams\Lti\Models\LtiLaunch::query()->where('direction', 'tool')->where('status', 'ok')->count(),
            'failed' => \Ulams\Lti\Models\LtiLaunch::query()->where('direction', 'tool')->where('status', 'failed')->pluck('error')->all(),
        ]);
        break;

    default:
        fwrite(STDERR, "usage: ulams-setup.php seed | platform '<json>' | grade-target <course id> | tool <course id> '<json>' | scores <topic id> | launches\n");
        exit(2);
}
