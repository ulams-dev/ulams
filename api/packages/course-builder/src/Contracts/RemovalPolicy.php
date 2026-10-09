<?php

namespace Ulams\CourseBuilder\Contracts;

/**
 * Decides, per LMS entity the applier is about to remove, whether it may be deleted. Phase 2 deletes
 * everything; Living Course keeps entities that learners have data on (ADR 0033): topics and
 * lessons are deactivated, GIFT questions are archived.
 */
interface RemovalPolicy
{
    /** @param string $entityType lesson | topic | quiz_topic | gift_question | page */
    public function shouldDelete(string $entityType, int $entityId): bool;
}
