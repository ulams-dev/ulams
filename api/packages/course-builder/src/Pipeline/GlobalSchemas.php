<?php

namespace Ulams\CourseBuilder\Pipeline;

/**
 * Output schemas of the whole-course edit steps (L2-15). The model returns only text, keyed by the ids
 * of the input; ids, citations, types and which answer is correct come from the original.
 */
final class GlobalSchemas
{
    private static function o(array $props): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => array_keys($props), 'properties' => $props];
    }

    private static function s(int $max, int $min = 0): array
    {
        return ['type' => 'string', 'minLength' => $min, 'maxLength' => $max];
    }

    private static function questions(): array
    {
        return ['type' => 'array', 'maxItems' => 50, 'items' => self::o([
            'id' => ['type' => 'string'],
            'stem' => self::s(1000, 3),
            'options' => ['type' => 'array', 'maxItems' => 8, 'items' => self::o(['id' => ['type' => 'string'], 'text' => self::s(500, 1)])],
            'explanation' => self::s(2000, 3),
        ])];
    }

    /** @param string $step lesson | course | final_test */
    public static function for(string $step): array
    {
        $properties = match ($step) {
            'lesson' => [
                'title' => self::s(200, 2),
                'summary' => self::s(600),
                'objectives' => ['type' => 'array', 'maxItems' => 8, 'items' => self::o(['id' => ['type' => 'string'], 'text' => self::s(400, 3)])],
                'blocks' => ['type' => 'array', 'maxItems' => 60, 'items' => self::o(['id' => ['type' => 'string'], 'markdown' => self::s(20000, 1)])],
                'questions' => self::questions(),
                'selfChecks' => self::questions(),
            ],
            'course' => [
                'title' => self::s(200, 3),
                'subtitle' => self::s(300),
                'description' => self::s(4000),
                'objectives' => ['type' => 'array', 'maxItems' => 12, 'items' => self::o(['id' => ['type' => 'string'], 'text' => self::s(400, 3)])],
                'modules' => ['type' => 'array', 'maxItems' => 20, 'items' => self::o(['id' => ['type' => 'string'], 'title' => self::s(200, 2), 'summary' => self::s(600)])],
                'faq' => ['type' => 'array', 'maxItems' => 12, 'items' => self::o(['question' => self::s(300), 'answer' => self::s(2000)])],
            ],
            'final_test' => ['questions' => self::questions()],
        };

        return ['$schema' => 'https://json-schema.org/draft/2020-12/schema', 'title' => "Global edit: {$step}"] + self::o($properties);
    }
}
