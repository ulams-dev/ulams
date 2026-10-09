<?php

namespace Ulams\LivingCourse\Analysis;

use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Pipeline\PatchService;
use Ulams\LivingCourse\Diff\TokenDiff;

/**
 * Semantic checks of an `update` answer (plan 8.2), run after the JSON Schema. Errors go back to the
 * model once (repair); a second failure fails the group step.
 */
final class UpdateValidator
{
    /**
     * @param array<string,mixed> $data decoded output
     * @param array<string,array{type:string,node:array,removedOnly:bool}> $expected the group's elements by id
     * @param array<string,string> $known fragment id => text, of the new revision
     * @param string[] $objectiveIds objective ids the replacements may refer to
     * @return string[]
     */
    public static function validate(array $data, array $expected, array $known, array $objectiveIds): array
    {
        $errors = [];
        $seen = [];
        $knownIds = array_fill_keys(array_keys($known), true);
        foreach ($data['items'] ?? [] as $n => $item) {
            $where = "items/{$n}";
            $id = (string) ($item['elementId'] ?? '');
            if (!isset($expected[$id])) {
                $errors[] = "{$where}: elementId {$id} is not an element of task_input";
                continue;
            }
            if (isset($seen[$id])) {
                $errors[] = "{$where}: element {$id} appears more than once";
            }
            $seen[$id] = true;
            $el = $expected[$id];
            $type = $el['type'];
            $decision = (string) ($item['decision'] ?? '');

            $errors = [...$errors, ...self::reason((string) ($item['reason'] ?? ''), $where), ...Checks::citations($item['fragmentIds'] ?? [], $knownIds, "{$where}/fragmentIds", 0)];

            $payloads = array_filter(['block' => $item['block'] ?? null, 'question' => $item['question'] ?? null, 'objective' => $item['objective'] ?? null], fn ($v) => $v !== null);
            if ($decision === 'update') {
                if (array_keys($payloads) !== [$type]) {
                    $errors[] = "{$where}: an update of a {$type} must fill only `{$type}`";
                    continue;
                }
                $errors = [...$errors, ...self::replacement($type, $payloads[$type], $known, $knownIds, $objectiveIds, $where)];
            } elseif ($payloads !== []) {
                $errors[] = "{$where}: `{$decision}` must not carry a replacement";
            }
            if ($decision === 'remove') {
                if ($type === 'objective') {
                    $errors[] = "{$where}: an objective cannot be removed; update it or keep it";
                } elseif (!$el['removedOnly']) {
                    $errors[] = "{$where}: remove only elements whose cited source passages were all removed (this one also cites passages that still exist)";
                }
            }

            $status = (string) ($item['answerStatus'] ?? '');
            if ($type === 'question') {
                if ($status === 'not_a_question') {
                    $errors[] = "{$where}: answerStatus of a question must be unchanged, changed or unsure";
                } elseif ($status === 'changed' && $decision === 'update' && !AnswerChange::detect($el['node'], (array) ($item['question'] ?? []))) {
                    $errors[] = "{$where}: answerStatus is `changed` but the correct answers in your replacement are the same as before; change them or say `unchanged`";
                } elseif ($status === 'changed' && $decision !== 'update') {
                    $errors[] = "{$where}: answerStatus `changed` needs a replacement question";
                }
            } elseif ($status !== 'not_a_question') {
                $errors[] = "{$where}: answerStatus must be `not_a_question` for a {$type}";
            }
        }
        foreach (array_keys($expected) as $id) {
            if (!isset($seen[$id])) {
                $errors[] = "element {$id} is missing from items; every element of task_input needs exactly one item";
            }
        }

        return array_slice($errors, 0, 30);
    }

    /** @return string[] */
    private static function reason(string $reason, string $where): array
    {
        $errors = Checks::markup($reason, "{$where}/reason");
        if (preg_match('~https?://|www\.~i', $reason)) {
            $errors[] = "{$where}/reason: must not contain links";
        }
        if (preg_match('/[`*#\[\]]/', $reason)) {
            $errors[] = "{$where}/reason: plain text only, no Markdown";
        }

        return $errors;
    }

    /**
     * @param array<string,string> $known
     * @param array<string,bool> $knownIds
     * @return string[]
     */
    private static function replacement(string $type, array $r, array $known, array $knownIds, array $objectiveIds, string $where): array
    {
        if ($type === 'objective') {
            return [...Checks::markup((string) ($r['text'] ?? ''), "{$where}/objective/text"), ...Checks::citations($r['citations'] ?? [], $knownIds, "{$where}/objective")];
        }
        $errors = array_map(fn ($e) => "{$where}: {$e}", PatchService::validate($type, $r, $knownIds, $objectiveIds));
        if ($type === 'question') {
            $texts = array_filter(array_map(fn ($id) => $known[$id] ?? '', $r['citations'] ?? []));
            $errors = [...$errors, ...Checks::quizSupport($r, $texts, "{$where}/question", (int) config('course_builder.quiz_support_min_overlap', 2))];
        }

        return $errors;
    }

    /**
     * How big the change is, by code (plan 8.2): `minor` when the model says minor and every changed
     * text is at least 85 % alike, `major` otherwise; `answer_changed` and `removed` win.
     *
     * @param array<string,mixed> $item validated output item
     * @param array<string,mixed> $before the element before
     * @param array<string,mixed>|null $after the replacement merged into an element
     */
    public static function changeClass(array $item, string $type, array $before, ?array $after): string
    {
        return match ((string) $item['decision']) {
            'remove' => 'removed',
            'no_change' => 'none',
            default => match (true) {
                $type === 'question' && $after !== null && AnswerChange::detect($before, $after) => 'answer_changed',
                $item['severity'] === 'minor' && $after !== null && self::similarity($type, $before, $after) >= 0.85 => 'minor',
                default => 'major',
            },
        };
    }

    /** Lowest similarity over the text fields of the element (1 = identical). */
    public static function similarity(string $type, array $before, array $after): float
    {
        $fields = match ($type) {
            'block' => ['markdown'],
            'question' => ['stem', 'explanation'],
            default => ['text'],
        };
        $min = 1.0;
        foreach ($fields as $field) {
            $a = (string) ($before[$field] ?? '');
            $b = (string) ($after[$field] ?? '');
            if ($a === $b) {
                continue;
            }
            $diff = TokenDiff::diff($a, $b);
            $min = min($min, 1 - max($diff['removed'], $diff['added']) / max(1, $diff['oldCount']));
        }
        if ($type === 'question') {
            $a = implode('|', array_map(fn ($o) => $o['text'], $before['options'] ?? []));
            $b = implode('|', array_map(fn ($o) => $o['text'], $after['options'] ?? []));
            if ($a !== $b) {
                $diff = TokenDiff::diff($a, $b);
                $min = min($min, 1 - max($diff['removed'], $diff['added']) / max(1, $diff['oldCount']));
            }
        }

        return $min;
    }
}
