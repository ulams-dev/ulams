<?php

namespace Ulams\CourseVariants\Services;

use Ulams\CourseVariants\Contracts\PlacementSource;
use Ulams\CourseVariants\Exceptions\VariantException;
use Ulams\CourseVariants\Models\PlacementAttempt;
use Ulams\CourseVariants\Models\PlacementItem;
use Ulams\CourseVariants\Models\VariantGroup;

/**
 * The optional placement check (ADR 0192, plan section 11; EU AI Act: advisory only). Questions are existing, cited quiz
 * questions of the family's courses, tagged with a level; nothing is generated and nothing about the learner leaves the
 * server. The score is computed by code and proposes a variant with its reason; the learner confirms or picks another.
 * It never decides access or a level on its own.
 */
final class PlacementService
{
    public const MIN_ITEMS = 6;
    public const MAX_ITEMS = 12;
    /** Share of a level's questions a learner must answer correctly to be considered comfortable at it. */
    public const PASS = 0.7;
    private const TYPES = ['single', 'multiple', 'truefalse'];

    public function __construct(private readonly PlacementSource $source, private readonly AssignmentService $assignments)
    {
    }

    /** The levels that have at least one selectable course in the family. @return string[] ascending */
    public function levels(VariantGroup $group): array
    {
        $selectable = $this->assignments->selectableCourseIds($group);
        $levels = [];
        foreach (Levels::all($group) as $row) {
            if ($row['level'] !== null && in_array($row['courseId'], $selectable, true)) {
                $levels[$row['level']] = true;
            }
        }

        return array_values(array_filter(Levels::ORDER, fn (string $l) => isset($levels[$l])));
    }

    /** Whether the check can be offered: switched on, two levels or more, and enough questions. */
    public function available(VariantGroup $group): bool
    {
        return $group->placement_enabled && count($this->levels($group)) >= 2 && PlacementItem::query()->where('group_id', $group->id)->count() >= self::MIN_ITEMS;
    }

    /**
     * Chooses 6 to 12 questions, spread over the levels and over the objectives they test, from what the source offers.
     * Deterministic (stable ids), repeatable. Questions without a citation, without a single scorable answer key or of a
     * free-text type are skipped.
     *
     * @return array{items:int,levels:array<string,int>}
     */
    public function rebuild(VariantGroup $group): array
    {
        $levels = $this->levels($group);
        $courses = array_values(array_filter(Levels::all($group), fn (array $r) => $r['level'] !== null));
        $byLevel = array_fill_keys($levels, []);
        $seen = [];
        foreach ($levels === [] ? [] : $this->source->questions(array_map(fn (array $r) => ['courseId' => $r['courseId'], 'level' => $r['level']], $courses)) as $q) {
            $question = $q['question'];
            $options = (array) ($question['options'] ?? []);
            $correct = array_filter($options, fn ($o) => !empty($o['correct']));
            if (!isset($byLevel[$q['level']]) || isset($seen[$q['elementId']]) || !in_array($question['type'] ?? '', self::TYPES, true)
                || count($options) < 2 || $correct === [] || empty($question['citations'])) {
                continue;
            }
            $seen[$q['elementId']] = true;
            $byLevel[$q['level']][] = $q;
        }
        $quota = $levels === [] ? 0 : intdiv(self::MAX_ITEMS, count($levels));
        $chosen = [];
        foreach ($byLevel as $level => $candidates) {
            usort($candidates, fn (array $a, array $b) => strcmp($a['elementId'], $b['elementId']));
            $covered = [];
            $picked = [];
            // first the questions that test an objective nobody picked yet, then the rest
            foreach ([true, false] as $onlyNew) {
                foreach ($candidates as $c) {
                    if (count($picked) >= $quota || isset($picked[$c['elementId']])) {
                        continue;
                    }
                    $objectives = array_values((array) ($c['question']['objectiveIds'] ?? []));
                    $new = array_diff($objectives, $covered) !== [] || $objectives === [];
                    if ($onlyNew && !$new) {
                        continue;
                    }
                    $picked[$c['elementId']] = $c;
                    $covered = array_merge($covered, $objectives);
                }
            }
            array_push($chosen, ...array_values($picked));
        }
        $total = count($chosen);
        if ($total < self::MIN_ITEMS) {
            $chosen = []; // too few to say anything: the check is not offered rather than offered on thin evidence
        }
        PlacementItem::query()->where('group_id', $group->id)->delete();
        foreach ($chosen as $position => $c) {
            PlacementItem::query()->create([
                'group_id' => $group->id, 'level' => $c['level'], 'element_id' => $c['elementId'], 'source_course_id' => $c['courseId'],
                'question' => $c['question'], 'position' => $position,
            ]);
        }
        $counts = [];
        foreach ($chosen as $c) {
            $counts[$c['level']] = ($counts[$c['level']] ?? 0) + 1;
        }

        return ['items' => count($chosen), 'levels' => $counts];
    }

    /**
     * The questions as a learner sees them: no answer key, no explanation, and not the level they belong to.
     *
     * @return array<int,array{id:int,type:string,stem:string,options:array<int,array{id:string,text:string}>}>
     */
    public function questions(VariantGroup $group): array
    {
        return PlacementItem::query()->where('group_id', $group->id)->orderBy('position')->get()->map(fn (PlacementItem $i) => [
            'id' => (int) $i->id,
            'type' => (string) $i->question['type'],
            'stem' => (string) $i->question['stem'],
            'options' => array_values(array_map(fn ($o) => ['id' => (string) $o['id'], 'text' => (string) $o['text']], (array) $i->question['options'])),
        ])->all();
    }

    /**
     * Scores an attempt by code and stores the result (not the answers). Answers: item id => option ids chosen. An item
     * without an answer counts as wrong.
     *
     * @param array<int|string,array<int,string>|string> $answers
     */
    public function attempt(int $userId, VariantGroup $group, array $answers, ?int $currentCourseId = null): PlacementAttempt
    {
        if (!$this->available($group)) {
            throw VariantException::invalid('The placement check is not available for this course.');
        }
        $scores = [];
        foreach (PlacementItem::query()->where('group_id', $group->id)->get() as $item) {
            $given = array_values(array_map('strval', (array) ($answers[$item->id] ?? $answers[(string) $item->id] ?? [])));
            $correct = array_values(array_map(fn ($o) => (string) $o['id'], array_filter((array) $item->question['options'], fn ($o) => !empty($o['correct']))));
            sort($given);
            sort($correct);
            $scores[$item->level]['total'] = ($scores[$item->level]['total'] ?? 0) + 1;
            $scores[$item->level]['correct'] = ($scores[$item->level]['correct'] ?? 0) + ($given === $correct ? 1 : 0);
        }
        [$level, $basis] = $this->suggestLevel($scores);
        $role = $currentCourseId !== null ? Levels::of($group, $currentCourseId)['role'] : null;
        $courseId = $level !== null ? Levels::courseFor($group, $level, $role, $this->assignments->selectableCourseIds($group)) : null;

        return PlacementAttempt::query()->create([
            'user_id' => $userId, 'group_id' => $group->id, 'scores' => $scores, 'suggested_course_id' => $courseId,
            'reason' => ['key' => 'placement', 'params' => ['level' => $level, 'correct' => $basis['correct'] ?? 0, 'total' => $basis['total'] ?? 0, 'basisLevel' => $basis['level'] ?? null]],
            'created_at' => now(),
        ]);
    }

    /**
     * Climbs the levels while the learner is comfortable at each (at least 70% right); the first level they are not
     * comfortable at stops the climb, and the lowest level is the floor.
     *
     * @param array<string,array{correct:int,total:int}> $scores
     *
     * @return array{0:?string,1:array{level?:string,correct?:int,total?:int}} the suggested level and the evidence that supports it
     */
    private function suggestLevel(array $scores): array
    {
        $suggest = null;
        $basis = [];
        foreach (Levels::ORDER as $level) {
            if (!isset($scores[$level])) {
                continue;
            }
            $suggest ??= $level;
            $s = $scores[$level];
            if ($s['total'] > 0 && $s['correct'] / $s['total'] >= self::PASS) {
                $suggest = $level;
                $basis = ['level' => $level, 'correct' => $s['correct'], 'total' => $s['total']];
            } else {
                $basis = $basis === [] ? ['level' => $level, 'correct' => $s['correct'], 'total' => $s['total']] : $basis;
                break;
            }
        }

        return [$suggest, $basis];
    }
}
