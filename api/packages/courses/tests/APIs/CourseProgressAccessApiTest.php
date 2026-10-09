<?php

namespace Ulams\Courses\Tests\APIs;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\Group;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Tests\Models\User;
use Ulams\Courses\Tests\TestCase;

/**
 * Progress is read and written only for a course the user attends: `GET|PATCH
 * /api/courses/progress/{course}` and `PUT /api/courses/progress/{topic}/ping`.
 */
class CourseProgressAccessApiTest extends TestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

    private Course $course;
    private Topic $topic;
    private Course $otherCourse;
    private Topic $otherTopic;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->course, $this->topic] = $this->publishedCourse();
        [$this->otherCourse, $this->otherTopic] = $this->publishedCourse();
    }

    private function publishedCourse(): array
    {
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true]);

        return [$course, $topic];
    }

    private function enrolled(): User
    {
        $user = User::factory()->create();
        $user->courses()->attach($this->course->getKey());

        return $user;
    }

    private function patchProgress(?User $user, int $courseId, array $topicIds)
    {
        $payload = ['progress' => array_map(fn (int $id) => ['topic_id' => $id, 'status' => 1], $topicIds)];

        return ($user ? $this->actingAs($user, 'api') : $this)->patchJson('/api/courses/progress/' . $courseId, $payload);
    }

    private function ping(?User $user, int $topicId)
    {
        return ($user ? $this->actingAs($user, 'api') : $this)->putJson('/api/courses/progress/' . $topicId . '/ping');
    }

    public function test_an_enrolled_user_can_read_write_and_ping(): void
    {
        $user = $this->enrolled();

        $this->actingAs($user, 'api')->getJson('/api/courses/progress/' . $this->course->getKey())->assertOk();
        $this->patchProgress($user, $this->course->getKey(), [$this->topic->getKey()])->assertOk();
        $this->ping($user, $this->topic->getKey())->assertOk()->assertJsonPath('data.status', true);
    }

    public function test_a_user_enrolled_through_a_group_can_ping(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->attach($user);
        $this->course->groups()->save($group);

        $this->ping($user, $this->topic->getKey())->assertOk();
        $this->patchProgress($user, $this->course->getKey(), [$this->topic->getKey()])->assertOk();
    }

    public function test_an_admin_can_ping(): void
    {
        $this->ping($this->makeAdmin(), $this->topic->getKey())->assertOk();
    }

    public function test_a_user_without_access_is_refused_and_nothing_is_stored(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'api')->getJson('/api/courses/progress/' . $this->course->getKey())->assertForbidden();
        $this->patchProgress($stranger, $this->course->getKey(), [$this->topic->getKey()])->assertForbidden();
        $this->ping($stranger, $this->topic->getKey())->assertForbidden();

        $this->assertSame(0, CourseProgress::query()->where('user_id', $stranger->getKey())->count());
    }

    public function test_enrolment_in_another_course_gives_no_access(): void
    {
        $user = User::factory()->create();
        $user->courses()->attach($this->otherCourse->getKey());

        $this->ping($user, $this->topic->getKey())->assertForbidden();
        $this->patchProgress($user, $this->course->getKey(), [$this->topic->getKey()])->assertForbidden();
    }

    public function test_an_expired_enrolment_gives_no_access(): void
    {
        $user = User::factory()->create();
        $user->courses()->attach($this->course->getKey(), ['end_date' => now()->subDay()]);

        $this->ping($user, $this->topic->getKey())->assertForbidden();
    }

    public function test_topics_of_another_course_cannot_be_progressed_through_an_accessible_one(): void
    {
        $user = $this->enrolled();

        $this->patchProgress($user, $this->course->getKey(), [$this->topic->getKey(), $this->otherTopic->getKey()])->assertForbidden();

        $this->assertSame(0, CourseProgress::query()->where('user_id', $user->getKey())->count());
    }

    public function test_guests_are_refused(): void
    {
        $this->getJson('/api/courses/progress/' . $this->course->getKey())->assertUnauthorized();
        $this->patchProgress(null, $this->course->getKey(), [$this->topic->getKey()])->assertUnauthorized();
        $this->ping(null, $this->topic->getKey())->assertUnauthorized();
    }

    public function test_unknown_courses_and_topics_are_not_found(): void
    {
        $user = $this->enrolled();

        $this->actingAs($user, 'api')->getJson('/api/courses/progress/999999')->assertNotFound();
        $this->patchProgress($user, 999999, [])->assertNotFound();
        $this->ping($user, 999999)->assertNotFound();
    }

    /**
     * Tenants have separate databases and Passport key pairs (ADR 0007), so a token of another tenant
     * is rejected, and an id that only exists in another tenant is unknown here. The tenants share this
     * process and test database; switching the key pair tells them apart (see the example plugin's test).
     */
    public function test_a_token_of_another_tenant_is_rejected(): void
    {
        $token = $this->enrolled()->createToken('test')->accessToken;

        $this->withToken($token)->putJson('/api/courses/progress/' . $this->topic->getKey() . '/ping')->assertOk();
        Auth::forgetGuards();

        $this->switchToTenantWithOwnKeys();

        $this->withToken($token)->putJson('/api/courses/progress/' . $this->topic->getKey() . '/ping')->assertUnauthorized();
        Auth::forgetGuards();
        $this->withToken($token)->patchJson('/api/courses/progress/' . $this->course->getKey(), ['progress' => []])->assertUnauthorized();
    }

    private function switchToTenantWithOwnKeys(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $public = openssl_pkey_get_details($key)['key'];

        config(['passport.private_key' => $private, 'passport.public_key' => $public]);
        foreach ([AuthorizationServer::class, ResourceServer::class, AccessTokenRepository::class] as $service) {
            $this->app->forgetInstance($service);
        }
        Auth::forgetGuards();
    }
}
