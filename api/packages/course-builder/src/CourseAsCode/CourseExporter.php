<?php

namespace Ulams\CourseBuilder\CourseAsCode;

use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Services\VersionService;

/**
 * The current blueprint of a course as a v3 document (ADR 0160): what the course-as-code CLI pulls.
 * Works with AI off. A course without a builder session has no blueprint yet (409 NOT_ADOPTED;
 * adoption of hand-made courses is a later milestone).
 */
final class CourseExporter
{
    public function __construct(private readonly BlueprintApplier $applier)
    {
    }

    /** @return array<string,mixed> */
    public function export(Session $session): array
    {
        $version = $session->currentVersion;
        if ($version === null || !in_array($version->kind, VersionService::CONTENT_KINDS, true)) {
            throw new CourseAsCodeException('This course has no content blueprint yet; finish the outline and generate the lessons first.', 409, 'NO_BLUEPRINT');
        }
        $document = Blueprint::upgrade($version->document);
        $drift = $session->course_id !== null ? $this->applier->drift($session, $document) : [];

        return [
            'courseId' => $session->course_id,
            'sessionId' => $session->id,
            'versionId' => $version->id,
            'versionNumber' => $version->number,
            'kind' => $version->kind,
            'status' => $version->status,
            'appliedVersionId' => $session->applied_version_id,
            'schemaVersion' => $document['schemaVersion'],
            'document' => $document,
            'drift' => array_map(fn ($key, $title) => ['element' => $key, 'title' => $title], array_keys($drift), array_values($drift)),
        ];
    }
}
