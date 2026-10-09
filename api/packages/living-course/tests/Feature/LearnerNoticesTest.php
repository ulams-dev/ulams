<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Ulams\Courses\Tests\Models\User;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\ElementStatus;
use Ulams\LivingCourse\Models\LearnerNotice;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Tests\TestCase;

/** The learner routes and the connection settings (plan 11.2). */
class LearnerNoticesTest extends TestCase
{
    private function notice(User $user, int $courseId, string $kind = 'topic_updated', int $topic = 7): LearnerNotice
    {
        return LearnerNotice::query()->create([
            'user_id' => $user->getKey(), 'course_id' => $courseId, 'topic_id' => $topic, 'gift_question_id' => 0, 'kind' => $kind,
            'proposal_id' => '01prop0sa1000000000000000a', 'message' => 'The ratio changed.', 'status' => 'open', 'created_at' => now(),
        ]);
    }

    public function testLearnersSeeAndDismissOnlyTheirOwnNotices(): void
    {
        $mine = User::factory()->create();
        $other = User::factory()->create();
        $a = $this->notice($mine, 5);
        $b = $this->notice($mine, 5, 'question_reattempt', 8);
        $this->notice($other, 5);
        $this->notice($mine, 6, 'topic_updated', 9);

        $list = $this->actingAs($mine, 'api')->getJson('/api/living-course/courses/5/notices')->assertOk()->json('data');
        $this->assertSame([$a->id, $b->id], array_column($list, 'id'));
        $this->assertSame('The ratio changed.', $list[0]['message']);
        $this->assertSame('topic_updated', $list[0]['kind']);

        $this->actingAs($mine, 'api')->postJson("/api/living-course/notices/{$a->id}/dismiss")->assertOk()->assertJsonPath('data.status', 'dismissed');
        $this->assertNotNull($a->refresh()->resolved_at);
        // reading a re-attempt notice does not close it: retaking the quiz does
        $this->actingAs($mine, 'api')->postJson("/api/living-course/notices/{$b->id}/dismiss")->assertOk()->assertJsonPath('data.status', 'open');
        $this->assertSame([$b->id], array_column($this->actingAs($mine, 'api')->getJson('/api/living-course/courses/5/notices')->json('data'), 'id'));

        $this->actingAs($other, 'api')->postJson("/api/living-course/notices/{$b->id}/dismiss")->assertStatus(404);
        $this->actingAs($mine, 'api')->postJson('/api/living-course/notices/999999/dismiss')->assertStatus(404);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/living-course/courses/5/notices')->assertStatus(401);
        $this->postJson("/api/living-course/notices/{$b->id}/dismiss")->assertStatus(401);
        $this->getJson('/api/living-course/courses/5/freshness')->assertStatus(401);
    }

    public function testFreshnessIsOffByDefaultAndNeverExposesReasonsOrText(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $courseId = (int) $session->course_id;
        $learner = User::factory()->create();
        $learner->courses()->sync([$courseId]);
        $outsider = User::factory()->create();
        config(['living_course.cost.auto_analyse_usd' => 0]);
        $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertCreated();
        ElementStatus::query()->where('session_id', $session->id)->update(['since' => now()->subDays(10)]);
        $url = "/api/living-course/courses/{$courseId}/freshness";

        $this->actingAs($learner, 'api')->getJson($url)->assertOk()->assertJsonPath('data.topics', []);

        $connection = $this->connectionOf($session);
        $this->actingAs($author, 'api')->putJson("/api/admin/living-course/connections/{$connection->id}", ['settings' => ['show_pending_to_learners' => true]])->assertOk();
        $response = $this->actingAs($learner, 'api')->getJson($url)->assertOk();
        $this->assertCount(4, $response->json('data.topics'), 'lessons 2.1, 2.2, 4.1 and 4.2 (its source section was removed)');
        $body = $response->getContent();
        $this->assertStringNotContainsString('1:16', $body);
        $this->assertStringNotContainsString('reason', $body);
        $this->assertSame(['topicId', 'since'], array_keys($response->json('data.topics.0')));

        // younger than the delay: not shown yet
        ElementStatus::query()->where('session_id', $session->id)->update(['since' => now()->subHour()]);
        $this->actingAs($learner, 'api')->getJson($url)->assertOk()->assertJsonPath('data.topics', []);
        // not enrolled: the course does not exist for them
        $this->actingAs($outsider, 'api')->getJson($url)->assertStatus(404);
    }

    public function testConnectionSettingsAreAuditedAndGuarded(): void
    {
        $owner = $this->author();
        $session = $this->sessionWithSource($owner);
        $connection = $this->connectionOf($session);
        $url = "/api/admin/living-course/connections/{$connection->id}";

        $response = $this->actingAs($owner, 'api')->putJson($url, ['autoAnalyse' => false, 'settings' => ['notify_learners_of_updates' => false, 'show_pending_to_learners' => true]])->assertOk();
        $this->assertFalse($response->json('data.autoAnalyse'));
        $this->assertFalse($response->json('data.settings.notify_learners_of_updates'));
        $this->assertTrue($response->json('data.settings.show_pending_to_learners'));
        $audit = AuditEntry::query()->where('action', 'connection.updated')->where('subject_id', $connection->id)->firstOrFail();
        $this->assertFalse($audit->data['changes']['autoAnalyse']);

        $this->actingAs($owner, 'api')->putJson($url, ['schedule' => 'daily'])->assertStatus(422);
        $this->actingAs($owner, 'api')->putJson($url, ['schedule' => 'fortnightly'])->assertStatus(422);
        $this->actingAs($owner, 'api')->putJson($url, ['status' => 'paused'])->assertOk()->assertJsonPath('data.status', 'paused');

        $this->actingAs($this->tutor(), 'api')->putJson($url, ['autoAnalyse' => true])->assertStatus(403);
        $this->actingAs($this->student(), 'api')->deleteJson($url)->assertStatus(403);
        $this->actingAs($this->readOnlyAdmin(), 'api')->putJson($url, ['autoAnalyse' => true])->assertStatus(403);
        $this->actingAs($owner, 'api')->putJson('/api/admin/living-course/connections/01aaaaaaaaaaaaaaaaaaaaaaaa', [])->assertStatus(404);

        $this->actingAs($owner, 'api')->deleteJson($url)->assertOk()->assertJsonPath('data.status', 'paused');
        $this->assertSame(1, AuditEntry::query()->where('action', 'connection.disconnected')->where('subject_id', $connection->id)->count());
        $this->assertSame(1, \Ulams\LivingCourse\Models\Revision::query()->where('source_id', $connection->source_id)->count(), 'the history is kept');
        $this->app['auth']->forgetGuards();
        $this->putJson($url, [])->assertStatus(401);
    }

    public function testTheLearnerNoteIsPlainTextOfLimitedLength(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        config(['living_course.cost.auto_analyse_usd' => 0]);
        $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertCreated();
        $proposal = Proposal::query()->where('session_id', $session->id)->firstOrFail();
        $url = "/api/admin/living-course/proposals/{$proposal->id}/learner-note";

        $this->actingAs($author, 'api')->putJson($url, ['note' => str_repeat('x', 501)])->assertStatus(422);
        $ok = $this->actingAs($author, 'api')->putJson($url, ['note' => '  Ratio is now 1:15 <script>alert(1)</script>  '])->assertOk();
        $this->assertSame('Ratio is now 1:15 <script>alert(1)</script>', $ok->json('data.learnerNote'), 'stored as text; every output escapes it');
        $this->actingAs($author, 'api')->putJson($url, ['note' => ''])->assertOk()->assertJsonPath('data.learnerNote', null);
        $this->actingAs($this->tutor(), 'api')->putJson($url, ['note' => 'x'])->assertStatus(403);
    }
}
