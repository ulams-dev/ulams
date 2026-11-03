<?php

namespace Ulams\CourseBuilder\Services;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseVariants\Contracts\PlacementSource;

/**
 * The questions for the placement check of a family (ADR 0192), taken from the quiz questions its courses already have,
 * so every one is cited and tied to an objective and nothing is generated. A question that is in the base blueprint
 * counts for the base course's level; a question a variant added or changed counts for that variant's level. Applied
 * versions only: a draft is never used to place learners.
 */
final class BuilderPlacementSource implements PlacementSource
{
    public function __construct(private readonly FamilyBranches $branches)
    {
    }

    public function questions(array $courses): array
    {
        $base = null;
        $baseQuestions = [];
        $out = [];
        $variants = [];
        foreach ($courses as $course) {
            $branch = $this->branches->branchOf($course['courseId']);
            $doc = $branch === null ? null : $this->branches->appliedDocument($branch);
            if ($branch === null || $doc === null) {
                continue;
            }
            if ($branch['variant'] === null) {
                $base = $course;
                $baseQuestions = $this->all($doc);
            } else {
                $variants[] = [$course, $this->all($doc)];
            }
        }
        if ($base !== null) {
            foreach ($baseQuestions as $id => $q) {
                $out[] = ['level' => $base['level'], 'courseId' => $base['courseId'], 'elementId' => $id, 'question' => $q];
            }
        }
        foreach ($variants as [$course, $questions]) {
            foreach ($questions as $id => $q) {
                $inBase = $baseQuestions[$id] ?? null;
                if ($inBase !== null && Blueprint::fingerprint($inBase) === Blueprint::fingerprint($q)) {
                    continue; // inherited unchanged: it tells nothing about this level
                }
                $out[] = ['level' => $course['level'], 'courseId' => $course['courseId'], 'elementId' => $inBase === null ? $id : "{$id}@{$course['courseId']}", 'question' => $q];
            }
        }

        return $out;
    }

    /** @return array<string,array> element id => question in the shape the placement stores */
    private function all(array $doc): array
    {
        $questions = [];
        foreach (Blueprint::lessons($doc) as $item) {
            foreach ($item['lesson']['quiz']['questions'] ?? [] as $q) {
                $questions[$q['id']] = $this->shape($q);
            }
        }
        foreach ($doc['finalTest']['questions'] ?? [] as $q) {
            $questions[$q['id']] = $this->shape($q);
        }

        return $questions;
    }

    private function shape(array $q): array
    {
        return [
            'type' => $q['type'], 'stem' => $q['stem'], 'explanation' => $q['explanation'] ?? '',
            'options' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $o['text'], 'correct' => (bool) $o['correct']], $q['options'] ?? []),
            'citations' => array_values($q['citations'] ?? []), 'objectiveIds' => array_values($q['objectiveIds'] ?? []),
        ];
    }
}
