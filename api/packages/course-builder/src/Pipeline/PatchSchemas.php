<?php

namespace Ulams\CourseBuilder\Pipeline;

use InvalidArgumentException;

/**
 * Output schema of an element patch: `{reply, ui, replacement}` where `replacement` is the
 * sub-schema of the selected element type. Child ids are strings ("" for a new child); the server
 * keeps known ids and assigns new ones, and forces the element's own id.
 */
final class PatchSchemas
{
    public const TYPES = ['course', 'module', 'lesson', 'block', 'question'];

    public static function for(string $type): array
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Elements of type {$type} cannot be edited in chat yet.");
        }
        $o = fn (array $props) => ['type' => 'object', 'additionalProperties' => false, 'required' => array_keys($props), 'properties' => $props];
        $s = fn (int $max, int $min = 1) => ['type' => 'string', 'minLength' => $min, 'maxLength' => $max];
        $frg = ['type' => 'array', 'items' => ['type' => 'string', 'pattern' => '^frg_[a-z2-7]{12}$'], 'minItems' => 1, 'maxItems' => 6];
        $ids = ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 6];
        $block = $o(['id' => ['type' => 'string'], 'kind' => ['type' => 'string', 'enum' => ['paragraph', 'callout', 'steps', 'code', 'table', 'example']], 'markdown' => $s(20000), 'citations' => $frg, 'objectiveIds' => $ids]);

        $replacement = match ($type) {
            'course' => $o(['title' => $s(200, 3), 'subtitle' => $s(300, 0), 'description' => $s(4000, 0)]),
            'module' => $o(['title' => $s(200, 2), 'summary' => $s(600, 0)]),
            'lesson' => $o(['title' => $s(200, 2), 'summary' => $s(600, 0), 'minutes' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 600], 'blocks' => ['type' => 'array', 'items' => $block, 'minItems' => 1, 'maxItems' => 12]]),
            'block' => $block,
            'question' => $o([
                'id' => ['type' => 'string'],
                'type' => ['type' => 'string', 'enum' => ['single', 'multiple', 'truefalse', 'short']],
                'stem' => $s(1000, 3),
                'options' => ['type' => 'array', 'items' => $o(['id' => ['type' => 'string'], 'text' => $s(500), 'correct' => ['type' => 'boolean']]), 'minItems' => 1, 'maxItems' => 6],
                'explanation' => $s(2000, 3),
                'citations' => $frg,
                'objectiveIds' => $ids,
            ]),
        };

        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'title' => "Patch of a {$type}",
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['reply', 'ui', 'replacement'],
            'properties' => [
                'reply' => $s(800),
                'ui' => ['type' => 'string', 'enum' => ['DiffView', 'QuizQuestionCard', 'LessonPreviewCard']],
                'replacement' => $replacement,
            ],
        ];
    }
}
