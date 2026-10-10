<?php

namespace Ulams\CourseBuilder\ContentTypes;

/**
 * How a lesson of the blueprint becomes LMS content (ADR 0050). Every content type renders
 * deterministically from structured, cited blueprint data: the model never writes LiaScript
 * Markdown, H5P parameters or topic fields. `topics()` is all the applier needs; the generation
 * pipeline asks `followUp()` for the extra step a type needs after the lesson text.
 */
interface ContentType
{
    public const FOLLOW_SELF_CHECKS = 'selfchecks';
    public const FOLLOW_INTERACTION = 'interaction';

    /** richtext | liascript | h5p | interactive */
    public function key(): string;

    public function label(): string;

    /** One line for authors: what learners get. */
    public function description(): string;

    /** Whether this installation can create the type now (package present, service reachable, tenant setting). */
    public function enabled(): bool;

    /** @return self::FOLLOW_*|null the generation step that follows the lesson text */
    public function followUp(): ?string;

    /**
     * The LMS topics of a lesson, in learner order. `slot` is `primary` for the lesson text (entity
     * type `topic`, element = the lesson id) or `interaction` (entity type `interaction_topic`,
     * element = the interaction id). `class` is the topic content class, `data` the fields the topic
     * repository takes (plus what the content writer needs to create the document or content).
     *
     * @param array<string,string> $labels fragment id → "§2.3 Title"
     * @return array<int,array{slot:string,element:string,class:class-string,data:array<string,mixed>}>
     */
    public function topics(array $lesson, array $labels, string $sourceTitle, string $language = 'en'): array;

    /**
     * Problems with the format-specific parts of a lesson, for the whole-document check before an apply.
     *
     * @param array<string,bool> $known fragment id → true
     * @return string[]
     */
    public function check(array $lesson, string $where, array $known): array;

    /** One line for the lesson preview, e.g. "LiaScript · 3 self-checks". */
    public function summary(array $lesson): string;
}
