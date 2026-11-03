<?php

namespace Ulams\LearnerInsights\Tests\Support;

use Ulams\Ai\Dto\DriverRequest;
use Ulams\LearnerInsights\Signals\SignalType;

/** Everything a request to the model carries, as one string, and the strings that must never be in it. */
final class ModelRequests
{
    /** The whole request: prompt, every block, the schema and any repair turns. */
    public static function text(DriverRequest $request): string
    {
        return implode("\n", [
            $request->system,
            ...array_map(fn ($block) => $block->text, $request->blocks),
            (string) json_encode($request->schema),
            ...array_map(fn (array $turn) => $turn['text'], $request->turns),
        ]);
    }

    /** @return string[] names of signal types (`quiz.answer`) and the vocabulary of stored statuses and reasons */
    public static function learnerVocabulary(): array
    {
        $types = array_values((new \ReflectionClass(SignalType::class))->getConstants());

        return [...array_filter($types, fn ($v) => is_string($v) && str_contains($v, '.')), 'learner_signals', 'learner_status', 'at_risk', 'on_track', 'failed_attempts', 'user_id', 'userId', 'learner_id'];
    }
}
