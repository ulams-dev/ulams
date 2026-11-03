<?php

namespace Ulams\CourseBuilder\Services;

use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseVariants\Contracts\TopicCorrespondence;

/**
 * Which topics of two variant courses are the same content (ADR 0192): the same element id (a variant keeps the base id
 * for everything it inherits or changes) and the same content, parent and type once applied. The order in the lesson is
 * not part of the comparison, because a variant that skips a lesson moves the others up without changing them. Quiz
 * topics are never paired: their attempts and scores are not copied, so a scored quiz is taken again in the new variant.
 */
final class BuilderTopicCorrespondence implements TopicCorrespondence
{
    private const TYPES = ['topic', 'interaction_topic'];

    public function __construct(private readonly FamilyBranches $branches, private readonly BlueprintApplier $applier)
    {
    }

    public function pairs(int $fromCourseId, int $toCourseId): array
    {
        $from = $this->branches->branchOf($fromCourseId);
        $to = $this->branches->branchOf($toCourseId);
        if ($from === null || $to === null || $from['session']->id !== $to['session']->id) {
            return [];
        }
        $a = $this->contents($from);
        $b = $this->contents($to);
        $pairs = [];
        foreach ($a as $key => [$topicId, $hash]) {
            if (isset($b[$key]) && $b[$key][1] === $hash) {
                $pairs[$topicId] = $b[$key][0];
            }
        }

        return $pairs;
    }

    /** @return array<string,array{0:int,1:string}> "type:element" => [topic id, content hash] of the topics the branch has live */
    private function contents(array $branch): array
    {
        $doc = $this->branches->appliedDocument($branch);
        if ($doc === null) {
            return [];
        }
        $desired = $this->applier->desired($doc);
        $out = [];
        foreach (EntityMapEntry::branch($branch['session']->id, $branch['variant']?->id)->whereIn('entity_type', self::TYPES)->whereNull('retired_at')->get() as $entry) {
            $key = "{$entry->entity_type}:{$entry->element_id}";
            if (isset($desired[$key])) {
                $out[$key] = [(int) $entry->entity_id, Blueprint::fingerprint([$desired[$key]['data'], $desired[$key]['parent']])];
            }
        }

        return $out;
    }
}
