<?php

namespace EscolaLms\TopicTypeGift\Events;

/**
 * Emitted when a fully graded attempt of a quiz flagged counts_to_grade is ready
 * to be reflected in the journal — on finish for auto-scored quizzes, or once a
 * lecturer grades the last open question. Re-grading a graded attempt emits it again.
 * Consumed (in pcg-grades) to upsert the partial grade for the journal.
 */
class QuizAttemptJournalGradeReadyEvent extends QuizAttemptEvent
{
}
