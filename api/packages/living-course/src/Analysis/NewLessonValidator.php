<?php

namespace Ulams\LivingCourse\Analysis;

use Ulams\CourseBuilder\Blueprint\Checks;

/**
 * Semantic checks of a `new_lesson` answer, run after the JSON Schema. Errors go back to the model
 * once (repair); a second failure fails the suggestion and leaves the course untouched.
 */
final class NewLessonValidator
{
    /**
     * @param array<string,mixed> $data decoded output
     * @param string[] $allowed fragment ids the lesson may cite
     * @param string[] $moduleIds ids of the course's modules
     * @return string[]
     */
    public static function validate(array $data, array $allowed, array $moduleIds): array
    {
        $errors = [];
        $allowedMap = array_fill_keys($allowed, true);
        $moduleId = $data['moduleId'] ?? null;
        if ($moduleId !== null && !in_array($moduleId, $moduleIds, true)) {
            $errors[] = 'moduleId is not a module of task_input.modules; use one of them or null with a moduleTitle';
        }
        if ($moduleId === null && trim((string) ($data['moduleTitle'] ?? '')) === '') {
            $errors[] = 'a new module needs a moduleTitle';
        }
        if ($moduleId !== null && ($data['moduleTitle'] ?? null) !== null) {
            $errors[] = 'moduleTitle must be null when an existing module is used';
        }
        foreach (['title', 'summary', 'moduleTitle'] as $field) {
            $value = (string) ($data[$field] ?? '');
            $errors = [...$errors, ...Checks::markup($value, $field)];
            if (preg_match('~https?://|www\.~i', $value) || preg_match('/[`*#\[\]]/', $value)) {
                $errors[] = "{$field}: plain text only, no Markdown or links";
            }
        }
        $objectives = (array) ($data['objectives'] ?? []);
        foreach ($objectives as $n => $objective) {
            $errors = [...$errors, ...Checks::markup((string) ($objective['text'] ?? ''), "objectives/{$n}/text"), ...Checks::citations((array) ($objective['citations'] ?? []), $allowedMap, "objectives/{$n}")];
        }
        $served = [];
        foreach ((array) ($data['blocks'] ?? []) as $n => $block) {
            $where = "blocks/{$n}";
            $errors = [...$errors, ...Checks::markup((string) ($block['markdown'] ?? ''), "{$where}/markdown"), ...Checks::citations((array) ($block['citations'] ?? []), $allowedMap, $where)];
            foreach ((array) ($block['objectiveIndexes'] ?? []) as $index) {
                if (!isset($objectives[$index])) {
                    $errors[] = "{$where}: objectiveIndexes {$index} is not one of your objectives";
                } else {
                    $served[$index] = true;
                }
            }
        }
        foreach (array_keys($objectives) as $index) {
            if (!isset($served[$index])) {
                $errors[] = "objectives/{$index}: no block serves this objective";
            }
        }

        return array_slice($errors, 0, 30);
    }
}
