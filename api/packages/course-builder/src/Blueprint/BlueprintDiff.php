<?php

namespace Ulams\CourseBuilder\Blueprint;

/**
 * Element-aware diff of two blueprint versions: elements are matched by id (never by position),
 * and each changed element lists its changed fields with before/after values. Text is compared as
 * a whole here; the UI marks the word-level differences (jsdiff in @ulams/ui).
 */
final class BlueprintDiff
{
    private const FIELD_LABELS = [
        'title' => 'Title', 'subtitle' => 'Subtitle', 'description' => 'Description', 'summary' => 'Summary',
        'minutes' => 'Minutes', 'text' => 'Text', 'markdown' => 'Content', 'kind' => 'Block type',
        'stem' => 'Question', 'explanation' => 'Explanation', 'type' => 'Question type', 'correct' => 'Correct answer',
        'citations' => 'Citations',
    ];

    /**
     * @return array<int,array{id:string,type:string,label:string,kind:string,ancestors:string[],fields:array<int,array{field:string,label:string,before:mixed,after:mixed}>}>
     */
    public static function compare(array $old, array $new): array
    {
        $a = self::flatten($old);
        $b = self::flatten($new);
        $changes = [];
        foreach ($b as $id => $node) {
            if (!isset($a[$id])) {
                $changes[] = ['id' => $id, 'type' => $node['type'], 'label' => $node['label'], 'kind' => 'added', 'ancestors' => $node['ancestors'],
                    'fields' => array_map(fn ($f, $v) => ['field' => $f, 'label' => self::FIELD_LABELS[$f] ?? $f, 'before' => null, 'after' => $v], array_keys($node['fields']), $node['fields'])];
                continue;
            }
            $fields = [];
            foreach ($node['fields'] as $field => $value) {
                $before = $a[$id]['fields'][$field] ?? null;
                if ($before !== $value) {
                    $fields[] = ['field' => $field, 'label' => self::FIELD_LABELS[$field] ?? $field, 'before' => $before, 'after' => $value];
                }
            }
            if ($fields !== []) {
                $changes[] = ['id' => $id, 'type' => $node['type'], 'label' => $node['label'], 'kind' => 'changed', 'ancestors' => $node['ancestors'], 'fields' => $fields];
            }
        }
        foreach ($a as $id => $node) {
            if (!isset($b[$id])) {
                $changes[] = ['id' => $id, 'type' => $node['type'], 'label' => $node['label'], 'kind' => 'removed', 'ancestors' => $node['ancestors'],
                    'fields' => array_map(fn ($f, $v) => ['field' => $f, 'label' => self::FIELD_LABELS[$f] ?? $f, 'before' => $v, 'after' => null], array_keys($node['fields']), $node['fields'])];
            }
        }

        return $changes;
    }

    /** Changes inside one element's subtree (the element itself included). */
    public static function forElement(array $changes, string $elementId): array
    {
        return array_values(array_filter($changes, fn ($c) => $c['id'] === $elementId || in_array($elementId, $c['ancestors'], true)));
    }

    /** @return array{added:int,changed:int,removed:int} */
    public static function counts(array $changes): array
    {
        $out = ['added' => 0, 'changed' => 0, 'removed' => 0];
        foreach ($changes as $c) {
            $out[$c['kind']]++;
        }

        return $out;
    }

    /**
     * @return array<string,array{type:string,label:string,ancestors:string[],fields:array<string,mixed>}>
     */
    public static function flatten(array $doc): array
    {
        $out = [];
        $course = $doc['course'] ?? [];
        if (isset($course['id'])) {
            $out[$course['id']] = ['type' => 'course', 'label' => 'Course', 'ancestors' => [], 'fields' => self::pick($course, ['title', 'subtitle', 'description'])];
            foreach ($course['objectives'] ?? [] as $i => $objective) {
                $out[$objective['id']] = ['type' => 'objective', 'label' => 'Course outcome ' . ($i + 1), 'ancestors' => [$course['id']], 'fields' => self::pick($objective, ['text', 'citations'])];
            }
        }
        foreach ($doc['modules'] ?? [] as $m => $module) {
            $mLabel = 'Module ' . ($m + 1);
            $out[$module['id']] = ['type' => 'module', 'label' => $mLabel, 'ancestors' => [], 'fields' => self::pick($module, ['title', 'summary'])];
            foreach ($module['lessons'] ?? [] as $l => $lesson) {
                $lLabel = 'Lesson ' . ($m + 1) . '.' . ($l + 1);
                $lAnc = [$module['id']];
                $out[$lesson['id']] = ['type' => 'lesson', 'label' => $lLabel, 'ancestors' => $lAnc, 'fields' => self::pick($lesson, ['title', 'summary', 'minutes', 'citations'])];
                foreach ($lesson['objectives'] ?? [] as $o => $objective) {
                    $out[$objective['id']] = ['type' => 'objective', 'label' => "{$lLabel} › objective " . ($o + 1), 'ancestors' => [...$lAnc, $lesson['id']], 'fields' => self::pick($objective, ['text', 'citations'])];
                }
                foreach ($lesson['blocks'] ?? [] as $b => $block) {
                    $out[$block['id']] = ['type' => 'block', 'label' => "{$lLabel} › block " . ($b + 1), 'ancestors' => [...$lAnc, $lesson['id']], 'fields' => self::pick($block, ['kind', 'markdown', 'citations'])];
                }
                if (is_array($lesson['quiz'] ?? null)) {
                    self::flattenQuiz($out, $lesson['quiz'], "{$lLabel} › ", [...$lAnc, $lesson['id']]);
                }
            }
        }
        if (is_array($doc['finalTest'] ?? null)) {
            self::flattenQuiz($out, $doc['finalTest'], 'Final test › ', []);
        }

        return $out;
    }

    private static function flattenQuiz(array &$out, array $quiz, string $prefix, array $ancestors): void
    {
        $out[$quiz['id']] = ['type' => 'quiz', 'label' => rtrim($prefix, ' ›') . ($prefix === 'Final test › ' ? '' : ' › quiz'), 'ancestors' => $ancestors, 'fields' => []];
        foreach ($quiz['questions'] ?? [] as $q => $question) {
            $qLabel = $prefix . 'Q' . ($q + 1);
            $qAnc = [...$ancestors, $quiz['id']];
            $out[$question['id']] = ['type' => 'question', 'label' => $qLabel, 'ancestors' => $qAnc, 'fields' => self::pick($question, ['type', 'stem', 'explanation', 'citations'])];
            foreach ($question['options'] ?? [] as $i => $option) {
                $out[$option['id']] = ['type' => 'option', 'label' => "{$qLabel} › option " . chr(65 + $i), 'ancestors' => [...$qAnc, $question['id']], 'fields' => self::pick($option, ['text', 'correct'])];
            }
        }
    }

    private static function pick(array $node, array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $node)) {
                $out[$f] = is_array($node[$f]) ? implode(', ', $node[$f]) : $node[$f];
            }
        }

        return $out;
    }
}
