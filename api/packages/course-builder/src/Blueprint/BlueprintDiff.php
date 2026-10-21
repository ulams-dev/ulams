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
        'citations' => 'Citations', 'origin' => 'Written by', 'contentType' => 'Lesson format', 'asset' => 'File',
        'library' => 'Activity type', 'data' => 'Activity content', 'caption' => 'Caption', 'packageId' => 'Interactive package',
        'startStep' => 'Start step', 'endStep' => 'End step',
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
                $out[$lesson['id']] = ['type' => 'lesson', 'label' => $lLabel, 'ancestors' => $lAnc, 'fields' => self::pick($lesson, ['title', 'summary', 'minutes', 'contentType', 'citations']) + self::origin($lesson) + self::asset($lesson)];
                foreach ($lesson['objectives'] ?? [] as $o => $objective) {
                    $out[$objective['id']] = ['type' => 'objective', 'label' => "{$lLabel} › objective " . ($o + 1), 'ancestors' => [...$lAnc, $lesson['id']], 'fields' => self::pick($objective, ['text', 'citations'])];
                }
                foreach ($lesson['blocks'] ?? [] as $b => $block) {
                    $out[$block['id']] = ['type' => 'block', 'label' => "{$lLabel} › block " . ($b + 1), 'ancestors' => [...$lAnc, $lesson['id']], 'fields' => self::pick($block, ['kind', 'markdown', 'citations']) + self::origin($block)];
                }
                foreach ($lesson['selfChecks'] ?? [] as $c => $check) {
                    self::flattenQuestion($out, $check, "{$lLabel} › check " . ($c + 1), [...$lAnc, $lesson['id']]);
                }
                if (is_array($lesson['interaction'] ?? null) && isset($lesson['interaction']['id'])) {
                    $interaction = $lesson['interaction'];
                    $out[$interaction['id']] = ['type' => 'interaction', 'label' => "{$lLabel} › activity", 'ancestors' => [...$lAnc, $lesson['id']],
                        'fields' => self::pick($interaction, ['kind', 'library', 'title', 'data', 'packageId', 'startStep', 'endStep', 'caption', 'citations']) + self::origin($interaction)];
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
            self::flattenQuestion($out, $question, $prefix . 'Q' . ($q + 1), [...$ancestors, $quiz['id']]);
        }
    }

    /** A quiz question or a lesson self-check, with its options. */
    private static function flattenQuestion(array &$out, array $question, string $label, array $ancestors): void
    {
        $out[$question['id']] = ['type' => 'question', 'label' => $label, 'ancestors' => $ancestors, 'fields' => self::pick($question, ['type', 'stem', 'explanation', 'citations']) + self::origin($question)];
        foreach ($question['options'] ?? [] as $i => $option) {
            $out[$option['id']] = ['type' => 'option', 'label' => "{$label} › option " . chr(65 + $i), 'ancestors' => [...$ancestors, $question['id']], 'fields' => self::pick($option, ['text', 'correct'])];
        }
    }

    /** Who wrote the element; absent means the AI did, so an `ai` to `author` change is visible. */
    private static function origin(array $node): array
    {
        return ['origin' => $node['origin'] ?? 'ai'];
    }

    /** The content hash of a lesson's file, so a replaced SCORM package or video shows up as a change. */
    private static function asset(array $lesson): array
    {
        return isset($lesson['asset']['sha256']) ? ['asset' => $lesson['asset']['path'] . ' (' . substr((string) $lesson['asset']['sha256'], 0, 12) . ')'] : [];
    }

    private static function pick(array $node, array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $node)) {
                $out[$f] = is_array($node[$f]) ? (array_is_list($node[$f]) && array_filter($node[$f], 'is_scalar') === $node[$f] ? implode(', ', $node[$f]) : self::encode($node[$f])) : $node[$f];
            }
        }

        return $out;
    }

    /** Nested values (an H5P activity's fields) as canonical JSON, so equal content compares equal. */
    private static function encode(array $value): string
    {
        return (string) json_encode(\Ulams\Ai\Fake\CassetteStore::canonical($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
