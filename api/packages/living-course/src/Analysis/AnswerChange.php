<?php

namespace Ulams\LivingCourse\Analysis;

use Ulams\LivingCourse\Diff\Normaliser;

/**
 * Whether the correct answer of a quiz question changed (plan 8.2, decision 7): decided by code
 * from the question before and after, never from what the model says about its own change.
 */
final class AnswerChange
{
    /**
     * @param array<string,mixed> $before question as in the blueprint
     * @param array<string,mixed> $after the replacement question
     */
    public static function detect(array $before, array $after): bool
    {
        if (($before['type'] ?? null) !== ($after['type'] ?? null)) {
            return true;
        }

        return self::correct($before) !== self::correct($after);
    }

    /** @return string[] normalised texts of the correct options, sorted */
    public static function correct(array $question): array
    {
        $texts = [];
        foreach ($question['options'] ?? [] as $option) {
            if (!empty($option['correct'])) {
                $texts[] = mb_strtolower(Normaliser::text((string) ($option['text'] ?? '')));
            }
        }
        sort($texts);

        return $texts;
    }
}
