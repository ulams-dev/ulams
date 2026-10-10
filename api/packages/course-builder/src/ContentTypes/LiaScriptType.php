<?php

namespace Ulams\CourseBuilder\ContentTypes;

use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\LiaScript\Models\LiaScriptTopic;
use Ulams\LiaScript\Services\Contracts\LiaScriptServiceContract;

/** A LiaScript document with the lesson text and inline self-checks, one LiaScript topic (ADR 0050). */
final class LiaScriptType implements ContentType
{
    public function key(): string
    {
        return 'liascript';
    }

    public function label(): string
    {
        return 'LiaScript';
    }

    public function description(): string
    {
        return 'An interactive lesson with questions inside the text that learners answer as they read.';
    }

    public function enabled(): bool
    {
        return interface_exists(LiaScriptServiceContract::class) && app()->bound(LiaScriptServiceContract::class)
            && config('course_builder.content_types.liascript', true) !== false;
    }

    public function followUp(): ?string
    {
        return self::FOLLOW_SELF_CHECKS;
    }

    public function topics(array $lesson, array $labels, string $sourceTitle, string $language = 'en'): array
    {
        return [['slot' => 'primary', 'element' => $lesson['id'], 'class' => LiaScriptTopic::class, 'data' => [
            'title' => $lesson['title'],
            'summary' => $lesson['summary'] ?? null,
            'duration' => $lesson['minutes'] . ' min',
            'markdown' => LiaScriptRenderer::render($lesson, $labels, $sourceTitle, $language),
        ]]];
    }

    public function check(array $lesson, string $where, array $known): array
    {
        $errors = [];
        foreach ($lesson['blocks'] ?? [] as $b => $block) {
            if (LiaScriptRenderer::unsafe((string) $block['markdown'])) {
                $errors[] = "{$where} block " . ($b + 1) . ': contains LiaScript code or an import, which lessons written by the builder cannot have';
            }
        }
        foreach ($lesson['selfChecks'] ?? [] as $q => $check) {
            $w = "{$where} check " . ($q + 1);
            $errors = [...$errors, ...Checks::citations($check['citations'] ?? [], $known, $w), ...Checks::questionShape($check, $w)];
            foreach (['stem', 'explanation'] as $field) {
                $errors = [...$errors, ...Checks::markup((string) ($check[$field] ?? ''), "{$w} {$field}")];
            }
        }
        if (($lesson['selfChecks'] ?? []) === []) {
            $errors[] = "warning: {$where}: a LiaScript lesson without self-checks reads like plain text";
        }

        return $errors;
    }

    public function summary(array $lesson): string
    {
        $n = count($lesson['selfChecks'] ?? []);

        return 'LiaScript' . ($n > 0 ? " · {$n} self-check" . ($n === 1 ? '' : 's') : '');
    }
}
