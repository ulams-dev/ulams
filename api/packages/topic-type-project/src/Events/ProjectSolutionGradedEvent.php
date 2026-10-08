<?php

namespace EscolaLms\TopicTypeProject\Events;

/**
 * Emitted when a solution of a project flagged counts_to_grade is graded by a lecturer.
 * Re-grading the solution emits it again.
 * Consumed (in pcg-grades) to upsert the partial grade for the journal.
 */
class ProjectSolutionGradedEvent extends ProjectSolutionEvent
{
}
