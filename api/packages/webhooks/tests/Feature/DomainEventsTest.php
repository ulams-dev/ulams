<?php

namespace Ulams\Webhooks\Tests\Feature;

use Ulams\Compliance\Events\PathCompleted;
use Ulams\Compliance\Events\TrainingAssigned;
use Ulams\Compliance\Events\TrainingCompleted;
use Ulams\Compliance\Events\TrainingOverdue;
use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Models\PathEnrollment;
use Ulams\Compliance\Services\PathService;
use Ulams\Courses\Events\CourseAssigned;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Models\Course;
use Ulams\KnowledgeExport\Events\KnowledgeExported;
use Ulams\KnowledgeExport\Models\KnowledgeExport;
use Ulams\LivingCourse\Events\ProposalApplied;
use Ulams\LivingCourse\Events\UpdateProposalReady;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\TemplatesPdf\Events\CertificateRevoked;
use Ulams\TemplatesPdf\Events\PdfCreated;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\Webhooks\Models\WebhookDelivery;
use Ulams\Webhooks\Support\EventCatalog;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Webhooks\Tests\TestCase;

/** The domain events that already exist reach the endpoints as versioned payloads. */
class DomainEventsTest extends TestCase
{
    use CreatesUsers;

    private function course(): Course
    {
        $course = new Course();
        $course->forceFill(['id' => 12, 'title' => 'Latte art']);

        return $course;
    }

    /** @return array<string,mixed> the `data.object` of the one request the receiver got */
    private function sentObject(string $type): array
    {
        $this->assertCount(1, $this->received);
        $event = json_decode((string) $this->received[0]->getBody(), true);
        $this->assertSame($type, $event['type']);

        return $event['data']['object'];
    }

    public function testEveryAvailableEventTypeHasAListener(): void
    {
        $listened = array_values(EventCatalog::listened());
        $available = array_keys(array_filter(EventCatalog::all(), fn ($e) => $e['status'] === 'available'));

        $this->assertEqualsCanonicalizing($available, $listened);
        $this->assertEqualsCanonicalizing(['*', ...$available], EventCatalog::subscribable());
    }

    public function testCourseCompleted(): void
    {
        $this->endpoint(['events' => ['course.completed']]);
        $this->receiver([200]);
        $learner = $this->makeStudent(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com']);

        event(new CourseFinished($learner, $this->course()));

        $object = $this->sentObject('course.completed');
        $this->assertSame(['id' => $learner->getKey(), 'email' => 'ada@example.com', 'name' => 'Ada Lovelace'], $object['user']);
        $this->assertSame(['id' => 12, 'title' => 'Latte art'], $object['course']);
        $this->assertNotEmpty($object['completed_at']);
    }

    public function testEnrolmentCreated(): void
    {
        $this->endpoint(['events' => ['enrolment.created']]);
        $this->receiver([200]);

        event(new CourseAssigned($this->makeStudent(), $this->course()));

        $object = $this->sentObject('enrolment.created');
        $this->assertSame(12, $object['course']['id']);
        $this->assertNotEmpty($object['created_at']);
    }

    public function testUpdateProposalReadyIsSentOncePerProposalEvenWithSeveralReviewers(): void
    {
        $this->endpoint(['events' => ['update_proposal.ready']]);
        $this->receiver([200, 200]);
        $data = ['sessionId' => 'sess1', 'proposalId' => 'prop1', 'courseTitle' => 'Latte art', 'elements' => 4, 'answerChecks' => 2, 'status' => 'ready'];

        UpdateProposalReady::dispatch($this->makeAdmin(), $data);
        UpdateProposalReady::dispatch($this->makeAdmin(), $data);

        $this->assertSame(1, WebhookDelivery::query()->count());
        $this->assertSame(['proposal_id' => 'prop1', 'session_id' => 'sess1', 'course_title' => 'Latte art', 'elements' => 4, 'answer_checks' => 2, 'status' => 'ready'], $this->sentObject('update_proposal.ready'));
    }

    public function testUpdateProposalApplied(): void
    {
        $this->endpoint(['events' => ['update_proposal.applied']]);
        $this->receiver([200]);
        $proposal = new Proposal();
        $proposal->forceFill(['id' => '01j9z8x3v7c5qk2m4n6p8r0t1w', 'session_id' => 'sess1', 'number' => 2, 'status' => 'applied', 'counts' => ['applied' => 3, 'notified' => true]]);

        event(new ProposalApplied($proposal));

        $this->assertSame(
            ['proposal_id' => '01j9z8x3v7c5qk2m4n6p8r0t1w', 'session_id' => 'sess1', 'number' => 2, 'status' => 'applied', 'counts' => ['applied' => 3]],
            $this->sentObject('update_proposal.applied'),
        );
    }

    public function testCertificateIssuedOnlyForCourseCertificates(): void
    {
        $this->endpoint(['events' => ['certificate.issued']]);
        $this->receiver([200]);
        $learner = $this->makeStudent();
        $other = new FabricPDF();
        $other->forceFill(['assignable_type' => 'Ulams\Webinar\Models\Webinar', 'assignable_id' => 3]);
        $certificate = new FabricPDF();
        $certificate->forceFill(['id' => 55, 'assignable_type' => Course::class, 'assignable_id' => 12]);

        event(new PdfCreated($learner, $other));
        $this->assertSame(0, WebhookDelivery::query()->count());
        event(new PdfCreated($learner, $certificate));

        $object = $this->sentObject('certificate.issued');
        $this->assertSame(55, $object['certificate_id']);
        $this->assertSame(12, $object['course_id']);
        $this->assertSame($learner->getKey(), $object['user']['id']);
    }

    public function testKnowledgeExportedIsSentOncePerExport(): void
    {
        $this->endpoint(['events' => ['knowledge.exported']]);
        $this->receiver([200, 200]);
        $export = new KnowledgeExport();
        $export->forceFill([
            'id' => '01j9z8x3v7c5qk2m4n6p8r0t1w', 'scope' => 'course', 'course_id' => 12, 'trigger' => 'auto', 'pack_hash' => str_repeat('b', 64),
            'courses' => 1, 'chunks' => 9, 'uncited' => 2, 'finished_at' => now(),
        ]);

        event(new KnowledgeExported($export));
        event(new KnowledgeExported($export));

        $object = $this->sentObject('knowledge.exported');
        $this->assertSame(['export_id' => '01j9z8x3v7c5qk2m4n6p8r0t1w', 'scope' => 'course', 'course_id' => 12, 'trigger' => 'auto', 'chunks' => 9, 'uncited_chunks' => 2], array_intersect_key($object, array_flip(['export_id', 'scope', 'course_id', 'trigger', 'chunks', 'uncited_chunks'])));
    }

    private function assignment(array $attributes = []): Assignment
    {
        $assignment = new Assignment();
        $assignment->forceFill($attributes + [
            'id' => 77, 'user_id' => 1, 'course_id' => 12, 'source' => 'rule', 'status' => 'assigned',
            'assigned_at' => now()->subDays(3), 'due_at' => now()->addDays(10),
        ]);

        return $assignment;
    }

    public function testTrainingAssignedIsSentOncePerAssignment(): void
    {
        $this->endpoint(['events' => ['training.assigned']]);
        $this->receiver([200, 200]);
        $learner = $this->makeStudent(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com']);
        $assignment = $this->assignment();

        event(new TrainingAssigned($learner, $this->course(), $assignment));
        event(new TrainingAssigned($learner, $this->course(), $assignment));

        $object = $this->sentObject('training.assigned');
        $this->assertSame(['id' => $learner->getKey(), 'email' => 'ada@example.com', 'name' => 'Ada Lovelace'], $object['user']);
        $this->assertSame(['id' => 12, 'title' => 'Latte art'], $object['course']);
        $this->assertSame(77, $object['assignment_id']);
        $this->assertSame('rule', $object['source']);
        $this->assertSame($assignment->due_at->toIso8601String(), $object['due_at']);
        $this->assertNull($object['path']);
        $this->assertNotEmpty($object['assigned_at']);
    }

    public function testTrainingEventsCarryTheLearningPathStep(): void
    {
        $this->endpoint(['events' => ['training.completed']]);
        $this->receiver([200]);
        $learner = $this->makeStudent();
        $first = Course::factory()->create();
        $second = Course::factory()->create();
        $path = app(PathService::class)->save(['title' => 'Onboarding', 'steps' => [['course_id' => $first->getKey()], ['course_id' => $second->getKey(), 'prerequisites' => [$first->getKey()]]]]);
        PathEnrollment::query()->create(['user_id' => $learner->getKey(), 'path_id' => $path->getKey(), 'source' => 'manual', 'enrolled_at' => now()]);
        $assignment = $this->assignment(['user_id' => $learner->getKey(), 'course_id' => $second->getKey(), 'status' => 'completed', 'completed_at' => now(), 'certificate_id' => 5]);

        event(new TrainingCompleted($learner, $second, $assignment));

        $object = $this->sentObject('training.completed');
        $this->assertSame(['id' => $path->getKey(), 'title' => 'Onboarding', 'step' => 2, 'steps' => 2], $object['path']);
        $this->assertSame(5, $object['certificate_id']);
        $this->assertSame($assignment->completed_at->toIso8601String(), $object['completed_at']);
    }

    public function testTrainingOverdue(): void
    {
        $this->endpoint(['events' => ['training.overdue']]);
        $this->receiver([200]);
        $due = now()->subDay();

        event(new TrainingOverdue($this->makeStudent(), $this->course(), $this->assignment(['due_at' => $due])));

        $object = $this->sentObject('training.overdue');
        $this->assertSame($due->toIso8601String(), $object['due_at']);
        $this->assertSame(77, $object['assignment_id']);
    }

    public function testPathCompletedIsSentOncePerEnrolment(): void
    {
        $this->endpoint(['events' => ['path.completed']]);
        $this->receiver([200, 200]);
        $learner = $this->makeStudent();
        $path = app(PathService::class)->save(['title' => 'Onboarding', 'steps' => [['course_id' => Course::factory()->create()->getKey()]]]);
        $enrollment = PathEnrollment::query()->create(['user_id' => $learner->getKey(), 'path_id' => $path->getKey(), 'source' => 'manual', 'enrolled_at' => now(), 'completed_at' => now()]);

        event(new PathCompleted($learner, $enrollment));
        event(new PathCompleted($learner, $enrollment));

        $object = $this->sentObject('path.completed');
        $this->assertSame(['id' => $path->getKey(), 'title' => 'Onboarding', 'steps' => 1], $object['path']);
        $this->assertSame($enrollment->getKey(), $object['enrollment_id']);
        $this->assertSame($learner->getKey(), $object['user']['id']);
    }

    public function testCertificateRevokedOnlyForCourseCertificates(): void
    {
        $this->endpoint(['events' => ['certificate.revoked']]);
        $this->receiver([200, 200]);
        $learner = $this->makeStudent();
        $other = FabricPDF::factory()->createOne(['user_id' => $learner->getKey(), 'assignable_type' => 'Ulams\Webinar\Models\Webinar', 'assignable_id' => 3]);
        $certificate = FabricPDF::factory()->createOne(['user_id' => $learner->getKey(), 'assignable_type' => Course::class, 'assignable_id' => 12, 'revoked_at' => now(), 'revoked_reason' => 'Superseded by policy version 2']);

        event(new CertificateRevoked($other));
        $this->assertSame(0, WebhookDelivery::query()->count());
        event(new CertificateRevoked($certificate));
        event(new CertificateRevoked($certificate));

        $object = $this->sentObject('certificate.revoked');
        $this->assertSame($certificate->getKey(), $object['certificate_id']);
        $this->assertSame(12, (int) $object['course_id']);
        $this->assertSame('Superseded by policy version 2', $object['reason']);
        $this->assertSame($learner->getKey(), $object['user']['id']);
    }

    public function testAnEndpointThatDoesNotSubscribeGetsNothing(): void
    {
        $this->endpoint(['events' => ['enrolment.created']]);

        event(new CourseFinished($this->makeStudent(), $this->course()));

        $this->assertSame(0, WebhookDelivery::query()->count());
    }
}
