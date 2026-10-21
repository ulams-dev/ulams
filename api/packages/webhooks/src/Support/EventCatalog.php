<?php

namespace Ulams\Webhooks\Support;

use Throwable;

/**
 * The event types a tenant can subscribe to, and how each domain event becomes a payload. Only events
 * that already exist as domain events are wired; the rest are listed as `planned` so the docs and the
 * API tell the same story. The classes are named as strings so the package loads without the packages
 * it listens to, and a listener never throws into the domain code that fired the event.
 *
 * Payloads are versioned (`api_version` of the endpoint). A version only ever gains fields; a breaking
 * change gets a new version, and an endpoint keeps receiving the version it was created with.
 */
final class EventCatalog
{
    public const WILDCARD = '*';

    public const TEST_EVENT = 'webhook.test';

    private const DOMAIN = [
        'course.completed' => [
            'class' => 'Ulams\Courses\Events\CourseFinished',
            'description' => 'A learner finished a course.',
        ],
        'enrolment.created' => [
            'class' => 'Ulams\Courses\Events\CourseAssigned',
            'description' => 'A learner was given access to a course (assigned by an admin or a group, or granted by an order).',
        ],
        'update_proposal.ready' => [
            'class' => 'Ulams\LivingCourse\Events\UpdateProposalReady',
            'description' => 'A Living Course update proposal is ready for review (sent once per proposal).',
        ],
        'update_proposal.applied' => [
            'class' => 'Ulams\LivingCourse\Events\ProposalApplied',
            'description' => 'An update proposal was reviewed and applied to the course.',
        ],
        'certificate.issued' => [
            'class' => 'Ulams\TemplatesPdf\Events\PdfCreated',
            'description' => 'A certificate PDF was generated for a learner.',
        ],
        'knowledge.exported' => [
            'class' => 'Ulams\KnowledgeExport\Events\KnowledgeExported',
            'description' => 'A knowledge pack (cited chunks for RAG) was written for a course or the whole tenant; an automatic re-export that finds nothing new sends nothing.',
        ],
    ];

    private const PLANNED = [
        'course.published' => 'A course was published.',
        'course.started' => 'A learner opened a course for the first time.',
        'order.paid' => 'An order was paid and its access granted (Phase 6).',
        'order.refunded' => 'An order was refunded and its access revoked (Phase 6).',
        'learner.registered' => 'A learner account was created.',
    ];

    /** @return array<string,array{type:string,description:string,status:string}> */
    public static function all(): array
    {
        $all = [];
        foreach (self::DOMAIN as $type => $info) {
            $all[$type] = ['type' => $type, 'description' => $info['description'], 'status' => 'available'];
        }
        foreach (self::PLANNED as $type => $description) {
            $all[$type] = ['type' => $type, 'description' => $description, 'status' => 'planned'];
        }
        $all[self::TEST_EVENT] = ['type' => self::TEST_EVENT, 'description' => 'Sent by the test button; never delivered to subscribers.', 'status' => 'test'];
        ksort($all);

        return $all;
    }

    /** @return list<string> the types an endpoint may subscribe to (the wildcard included) */
    public static function subscribable(): array
    {
        return [self::WILDCARD, ...array_keys(self::DOMAIN)];
    }

    /** @return array<string,string> domain event class => event type */
    public static function listened(): array
    {
        $map = [];
        foreach (self::DOMAIN as $type => $info) {
            $map[$info['class']] = $type;
        }

        return $map;
    }

    /**
     * The payload object of a domain event, or null to skip it (not a certificate, already sent).
     *
     * @return array{object:array<string,mixed>,key:?string}|null
     */
    public static function payloadFor(string $type, object $event): ?array
    {
        try {
            return match ($type) {
                'course.completed' => ['object' => self::enrolment($event, ['completed_at' => now()->toIso8601String()]), 'key' => null],
                'enrolment.created' => ['object' => self::enrolment($event, ['created_at' => now()->toIso8601String()]), 'key' => null],
                'update_proposal.ready' => self::proposalReady($event),
                'update_proposal.applied' => self::proposalApplied($event),
                'certificate.issued' => self::certificate($event),
                'knowledge.exported' => self::knowledgeExported($event),
                default => null,
            };
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** A made-up but well-formed sample for the test button, per type. */
    public static function sample(string $type): array
    {
        $user = ['id' => 1, 'email' => 'learner@example.com', 'name' => 'Sample Learner'];
        $course = ['id' => 1, 'title' => 'Sample course'];

        return match ($type) {
            'course.completed' => ['user' => $user, 'course' => $course, 'completed_at' => now()->toIso8601String()],
            'enrolment.created' => ['user' => $user, 'course' => $course, 'created_at' => now()->toIso8601String()],
            'update_proposal.ready' => ['proposal_id' => '01SAMPLE0PROPOSAL0000000000', 'session_id' => '01SAMPLE0SESSION00000000000', 'course_title' => 'Sample course', 'elements' => 3, 'answer_checks' => 1, 'status' => 'ready'],
            'update_proposal.applied' => ['proposal_id' => '01SAMPLE0PROPOSAL0000000000', 'session_id' => '01SAMPLE0SESSION00000000000', 'number' => 2, 'status' => 'applied', 'counts' => ['applied' => 3]],
            'certificate.issued' => ['user' => $user, 'certificate_id' => 1, 'course_id' => 1, 'issued_at' => now()->toIso8601String()],
            'knowledge.exported' => ['export_id' => '01SAMPLE0EXPORT00000000000', 'scope' => 'course', 'course_id' => 1, 'trigger' => 'auto', 'pack_hash' => str_repeat('a', 64), 'courses' => 1, 'chunks' => 12, 'uncited_chunks' => 2, 'finished_at' => now()->toIso8601String()],
            default => ['message' => 'This is a test event from ulams.'],
        };
    }

    /** @return array{id:int|string,email:?string,name:string} */
    private static function person(object $user): array
    {
        $name = trim(((string) ($user->first_name ?? '')) . ' ' . ((string) ($user->last_name ?? '')));

        return ['id' => $user->getKey(), 'email' => $user->email ?? null, 'name' => $name !== '' ? $name : (string) ($user->name ?? '')];
    }

    /** @param array<string,mixed> $extra */
    private static function enrolment(object $event, array $extra): array
    {
        $course = $event->getCourse();

        return ['user' => self::person($event->getUser()), 'course' => ['id' => $course->getKey(), 'title' => (string) $course->title]] + $extra;
    }

    /** @return array{object:array<string,mixed>,key:?string} */
    private static function proposalReady(object $event): array
    {
        $data = $event->toArray();
        $proposalId = (string) ($data['proposalId'] ?? '');

        return [
            'object' => [
                'proposal_id' => $proposalId,
                'session_id' => (string) ($data['sessionId'] ?? ''),
                'course_title' => (string) ($data['courseTitle'] ?? ''),
                'elements' => (int) ($data['elements'] ?? 0),
                'answer_checks' => (int) ($data['answerChecks'] ?? 0),
                'status' => (string) ($data['status'] ?? ''),
            ],
            // the event is dispatched once per reviewer; the receiver gets one webhook per proposal
            'key' => $proposalId !== '' ? 'update_proposal.ready:' . $proposalId : null,
        ];
    }

    /** @return array{object:array<string,mixed>,key:?string} */
    private static function proposalApplied(object $event): array
    {
        $proposal = $event->proposal;
        $counts = [];
        foreach ((array) $proposal->counts as $name => $value) {
            if (is_int($value)) {
                $counts[(string) $name] = $value;
            }
        }

        return [
            'object' => [
                'proposal_id' => (string) $proposal->id,
                'session_id' => (string) $proposal->session_id,
                'number' => (int) $proposal->number,
                'status' => (string) $proposal->status,
                'counts' => $counts,
            ],
            'key' => 'update_proposal.applied:' . $proposal->id,
        ];
    }

    /** @return array{object:array<string,mixed>,key:?string}|null */
    private static function certificate(object $event): ?array
    {
        $pdf = $event->getPdf();
        // PdfCreated also fires for other generated documents; only course certificates are events here
        if (!str_ends_with((string) $pdf->assignable_type, 'Course')) {
            return null;
        }

        return [
            'object' => [
                'user' => self::person($event->getUser()),
                'certificate_id' => $pdf->getKey(),
                'course_id' => $pdf->assignable_id,
                'issued_at' => now()->toIso8601String(),
            ],
            'key' => null,
        ];
    }

    /** @return array{object:array<string,mixed>,key:?string} */
    private static function knowledgeExported(object $event): array
    {
        $export = $event->export;

        return [
            'object' => [
                'export_id' => (string) $export->id,
                'scope' => (string) $export->scope,
                'course_id' => $export->course_id,
                'trigger' => (string) $export->trigger,
                'pack_hash' => (string) $export->pack_hash,
                'courses' => (int) $export->courses,
                'chunks' => (int) $export->chunks,
                'uncited_chunks' => (int) $export->uncited,
                'finished_at' => $export->finished_at?->toIso8601String() ?? now()->toIso8601String(),
            ],
            'key' => 'knowledge.exported:' . $export->id,
        ];
    }
}
