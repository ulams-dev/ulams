<?php

namespace Ulams\CourseBuilder\ContentTypes;

use Throwable;
use Ulams\CourseBuilder\Apply\LessonMarkdown;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\Interactive\Models\InteractivePackage;
use Ulams\Interactive\Models\InteractiveTopic;
use Ulams\TopicTypes\Models\TopicContent\RichText;

/**
 * Rich text lesson followed by an interactive package from the library (ADR 0086, 0087). The builder
 * never writes or edits a package: it chooses one the author or an administrator uploaded, picks the
 * steps to play and writes the cited text shown beside it. The topic is created through the topic
 * repository, so the observer that checks the steps and the pinned version applies as for any author.
 */
final class InteractiveType implements ContentType
{
    public function key(): string
    {
        return 'interactive';
    }

    public function label(): string
    {
        return 'Lesson with an interactive';
    }

    public function description(): string
    {
        return 'The lesson text followed by one of the interactive packages in your library, played between the steps you choose.';
    }

    public function enabled(): bool
    {
        if (!class_exists(InteractivePackage::class) || !config('ulams_interactive.enabled', true) || config('course_builder.content_types.interactive', true) === false) {
            return false;
        }
        try {
            return InteractivePackage::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function followUp(): ?string
    {
        return self::FOLLOW_INTERACTION;
    }

    /**
     * The packages the model may choose from, with their steps (the text alternatives are what the
     * model reads to decide which steps fit the lesson).
     *
     * @return array<int,array{id:int,title:string,steps:array<int,array{id:string,title:string,text:string}>}>
     */
    public static function library(int $limit = 20): array
    {
        $out = [];
        foreach (InteractivePackage::query()->orderByDesc('id')->limit($limit)->get() as $package) {
            $version = $package->current;
            if ($version === null) {
                continue;
            }
            $manifest = $version->manifest;
            $locale = (string) ($manifest['defaultLocale'] ?? 'en');
            $out[] = [
                'id' => (int) $package->id,
                'title' => (string) $package->title,
                'steps' => array_map(fn (array $s) => [
                    'id' => (string) $s['id'],
                    'title' => (string) (($s['title'][$locale] ?? reset($s['title'])) ?: $s['id']),
                    'text' => mb_substr((string) (($s['textAlternative'][$locale] ?? (is_array($s['textAlternative'] ?? null) ? reset($s['textAlternative']) : '')) ?: ''), 0, 300),
                ], array_slice((array) ($manifest['steps'] ?? []), 0, 40)),
            ];
        }

        return $out;
    }

    public function topics(array $lesson, array $labels, string $sourceTitle, string $language = 'en'): array
    {
        $topics = [['slot' => 'primary', 'element' => $lesson['id'], 'class' => RichText::class, 'data' => [
            'title' => $lesson['title'],
            'summary' => $lesson['summary'] ?? null,
            'duration' => $lesson['minutes'] . ' min',
            'value' => LessonMarkdown::render($lesson, $labels, $sourceTitle),
        ]]];
        $interaction = $lesson['interaction'] ?? null;
        if (is_array($interaction) && ($interaction['kind'] ?? '') === 'interactive') {
            $cited = [];
            foreach ($interaction['citations'] ?? [] as $id) {
                $cited[] = $labels[$id] ?? $id;
            }
            $caption = trim(LessonMarkdown::sanitise((string) $interaction['caption']));
            if ($cited !== []) {
                $caption .= "\n\n**Sources:** " . implode('; ', $cited);
            }
            $topics[] = ['slot' => 'interaction', 'element' => $interaction['id'], 'class' => InteractiveTopic::class, 'data' => array_filter([
                'title' => mb_substr((string) $interaction['title'], 0, 255),
                'value' => (int) $interaction['packageId'],
                'start_step' => $interaction['startStep'] ?? null,
                'end_step' => $interaction['endStep'] ?? null,
                'text' => $caption,
                'completion_rule' => 'on_range_end',
                'display' => 'inline',
            ], fn ($v) => $v !== null)];
        }

        return $topics;
    }

    public function check(array $lesson, string $where, array $known): array
    {
        $interaction = $lesson['interaction'] ?? null;
        if (!is_array($interaction) || ($interaction['kind'] ?? '') !== 'interactive') {
            return ["warning: {$where}: an interactive lesson without its interactive is applied as plain text"];
        }
        $w = "{$where} interactive";
        $errors = [...Checks::citations($interaction['citations'] ?? [], $known, $w), ...Checks::markup((string) $interaction['caption'], "{$w} text")];
        $package = InteractivePackage::query()->find((int) $interaction['packageId']);
        if ($package === null) {
            return [...$errors, "{$w}: package {$interaction['packageId']} is not in your library"];
        }
        $steps = $package->current?->stepIds() ?? [];
        foreach (['startStep', 'endStep'] as $field) {
            if (isset($interaction[$field]) && !in_array($interaction[$field], $steps, true)) {
                $errors[] = "{$w}: the step \"{$interaction[$field]}\" does not exist in the package";
            }
        }
        if (isset($interaction['startStep'], $interaction['endStep']) && array_search($interaction['startStep'], $steps, true) > array_search($interaction['endStep'], $steps, true)) {
            $errors[] = "{$w}: the end step comes before the start step";
        }

        return $errors;
    }

    public function summary(array $lesson): string
    {
        $i = $lesson['interaction'] ?? null;

        return 'Interactive' . (is_array($i) && ($i['kind'] ?? '') === 'interactive' ? ' · ' . $i['title'] : '');
    }
}
