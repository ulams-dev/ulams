<?php

namespace Ulams\Webhooks\Tests\Feature;

use Ulams\Courses\Events\CourseAssigned;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Models\Course;
use Ulams\LivingCourse\Events\ProposalApplied;
use Ulams\LivingCourse\Events\UpdateProposalReady;
use Ulams\LivingCourse\Models\Proposal;
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

    public function testAnEndpointThatDoesNotSubscribeGetsNothing(): void
    {
        $this->endpoint(['events' => ['enrolment.created']]);

        event(new CourseFinished($this->makeStudent(), $this->course()));

        $this->assertSame(0, WebhookDelivery::query()->count());
    }
}
