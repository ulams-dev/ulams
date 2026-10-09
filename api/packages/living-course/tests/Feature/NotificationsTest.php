<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Tests\Models\User;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\LivingCourse\Events\CourseContentUpdated;
use Ulams\LivingCourse\Events\SourceCheckFailing;
use Ulams\LivingCourse\Events\SourceRevisionDetected;
use Ulams\LivingCourse\Events\UpdateProposalApplied;
use Ulams\LivingCourse\Events\UpdateProposalReady;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Services\Notifier;
use Ulams\LivingCourse\Tests\TestCase;

/** Notification events (plan 10.2): in-app for everything, e-mail templates for three of them. */
class NotificationsTest extends TestCase
{
    private function uploadV2($author, $session): void
    {
        $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertCreated();
    }

    public function testTheAuthorIsToldOnceWhenAProposalIsReady(): void
    {
        Event::fake([SourceRevisionDetected::class, UpdateProposalReady::class, UpdateProposalApplied::class, CourseContentUpdated::class]);
        $author = $this->author();
        $session = $this->toApplied($author);

        $this->uploadV2($author, $session);

        Event::assertDispatchedTimes(SourceRevisionDetected::class, 1);
        Event::assertDispatchedTimes(UpdateProposalReady::class, 1);
        Event::assertDispatched(UpdateProposalReady::class, function (UpdateProposalReady $e) use ($author, $session) {
            $data = $e->toArray();
            $this->assertSame($author->getKey(), $e->getUser()->getKey());
            $this->assertSame($session->id, $data['sessionId']);
            $this->assertSame(22, $data['elements']);
            $this->assertSame(8, $data['answerChecks']);
            $this->assertSame('ready', $data['status']);
            // nothing from the source text reaches the notification
            $this->assertStringNotContainsString('1:16', json_encode(array_diff_key($data, ['user' => 1])));

            return true;
        });

        $proposal = Proposal::query()->where('session_id', $session->id)->firstOrFail();
        app(Notifier::class)->proposalReady($proposal);
        Event::assertDispatchedTimes(UpdateProposalReady::class, 1);

        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/apply")->assertStatus(202);
        Event::assertDispatchedTimes(UpdateProposalApplied::class, 1);
    }

    public function testAnAdminWithTheReviewPermissionWhoAuthorsTheCourseIsToldToo(): void
    {
        Event::fake([UpdateProposalReady::class]);
        $author = $this->author();
        $session = $this->toApplied($author);
        $admin = $this->admin();
        \Ulams\Courses\Models\Course::query()->findOrFail($session->course_id)->authors()->syncWithoutDetaching([$admin->getKey()]);

        $this->uploadV2($author, $session);

        $recipients = [];
        Event::assertDispatched(UpdateProposalReady::class, function (UpdateProposalReady $e) use (&$recipients) {
            $recipients[] = $e->getUser()->getKey();

            return true;
        });
        $this->assertEqualsCanonicalizing([$author->getKey(), $admin->getKey()], $recipients);
    }

    public function testLearnersGetOneEventPerCourseAndWeekAndOnlyWhenTheCourseInformsThem(): void
    {
        Event::fake([CourseContentUpdated::class]);
        Cache::flush();
        $author = $this->author();
        $session = $this->toApplied($author);
        $learner = User::factory()->create();
        $learner->courses()->sync([$session->course_id]);
        $topic = \Ulams\CourseBuilder\Models\EntityMapEntry::query()->where('session_id', $session->id)->where('entity_type', 'topic')->where('element_id', $session->currentVersion->document['modules'][1]['lessons'][0]['id'])->value('entity_id');
        CourseProgress::create(['user_id' => $learner->getKey(), 'topic_id' => $topic, 'status' => ProgressStatus::IN_PROGRESS]);
        $this->uploadV2($author, $session);
        $proposal = Proposal::query()->where('session_id', $session->id)->firstOrFail();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/apply")->assertStatus(202);

        Event::assertDispatchedTimes(CourseContentUpdated::class, 1);
        Event::assertDispatched(CourseContentUpdated::class, fn (CourseContentUpdated $e) => $e->getUser()->getKey() === $learner->getKey() && $e->toArray()['courseId'] === (int) $session->course_id);
        // a later proposal inside the week does not mail the learner again
        $this->assertSame(0, app(Notifier::class)->learnersNotified($proposal));
    }

    public function testLearnersAreNotToldWhenTheCourseTurnsNoticesOff(): void
    {
        Event::fake([CourseContentUpdated::class]);
        Cache::flush();
        $author = $this->author();
        $session = $this->toApplied($author);
        $learner = User::factory()->create();
        $learner->courses()->sync([$session->course_id]);
        $topic = \Ulams\CourseBuilder\Models\EntityMapEntry::query()->where('session_id', $session->id)->where('entity_type', 'topic')->where('element_id', $session->currentVersion->document['modules'][1]['lessons'][0]['id'])->value('entity_id');
        CourseProgress::create(['user_id' => $learner->getKey(), 'topic_id' => $topic, 'status' => ProgressStatus::COMPLETE]);
        $connection = $this->connectionOf($session);
        $this->actingAs($author, 'api')->putJson("/api/admin/living-course/connections/{$connection->id}", ['settings' => ['notify_learners_of_updates' => false]])->assertOk();
        $this->uploadV2($author, $session);
        $proposal = Proposal::query()->where('session_id', $session->id)->firstOrFail();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/apply")->assertStatus(202);

        Event::assertNotDispatched(CourseContentUpdated::class);
        // but the corrected question still reaches the learner who answered it: that notice is functional
        $this->assertSame(0, \Ulams\LivingCourse\Models\LearnerNotice::query()->where('kind', 'topic_updated')->count());
    }

    public function testAFailingSourceTellsTheAuthor(): void
    {
        Event::fake([SourceCheckFailing::class]);
        $session = $this->sessionWithSource($this->author());
        $connection = $this->connectionOf($session);
        $connection->forceFill(['last_error' => 'The repository answered 404.'])->save();

        app(Notifier::class)->checkFailing($connection);

        Event::assertDispatched(SourceCheckFailing::class, fn (SourceCheckFailing $e) => $e->toArray()['error'] === 'The repository answered 404.' && $e->toArray()['connector'] === 'upload');
    }
}
