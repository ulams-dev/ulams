<?php

namespace Ulams\CourseBuilder\ContentTypes;

use Ulams\CourseBuilder\Apply\LessonMarkdown;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\TopicTypes\Models\TopicContent\H5P;
use Ulams\TopicTypes\Models\TopicContent\RichText;

/**
 * Rich text lesson followed by one H5P activity from the allow-list (ADR 0050). The activity is
 * created through the H5P service and shown as an H5P topic after the text.
 */
final class H5pType implements ContentType
{
    public function __construct(private readonly H5pLibraries $libraries)
    {
    }

    public function key(): string
    {
        return 'h5p';
    }

    public function label(): string
    {
        return 'Lesson with an H5P activity';
    }

    public function description(): string
    {
        return 'The lesson text followed by a fill-in-the-blanks, drag-the-words or flash-card activity.';
    }

    public function enabled(): bool
    {
        return $this->libraries->installed() !== [];
    }

    public function followUp(): ?string
    {
        return self::FOLLOW_INTERACTION;
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
        if (is_array($interaction) && ($interaction['kind'] ?? '') === 'h5p') {
            // without a version when the service cannot say which is installed: its answer to the save then names the problem
            $uber = $this->libraries->installed()[$interaction['library']] ?? (string) $interaction['library'];
            $topics[] = ['slot' => 'interaction', 'element' => $interaction['id'], 'class' => H5P::class, 'data' => [
                'title' => mb_substr((string) $interaction['title'], 0, 255),
                'summary' => null,
                'duration' => null,
                'library' => $uber,
                'h5pParams' => H5pLibraries::params($interaction['library'], $interaction['data']),
                'h5pMetadata' => ['title' => mb_substr((string) $interaction['title'], 0, 255)],
            ]];
        }

        return $topics;
    }

    public function check(array $lesson, string $where, array $known): array
    {
        $interaction = $lesson['interaction'] ?? null;
        if (!is_array($interaction) || ($interaction['kind'] ?? '') !== 'h5p') {
            return ["warning: {$where}: an H5P lesson without its activity is applied as plain text"];
        }
        $w = "{$where} activity";
        $errors = [...Checks::citations($interaction['citations'] ?? [], $known, $w), ...H5pLibraries::errors((string) $interaction['library'], (array) $interaction['data'], $w)];
        if (!in_array($interaction['library'], $this->libraries->allowed(), true)) {
            $errors[] = "{$w}: {$interaction['library']} is not an allowed library";
        } elseif (!isset($this->libraries->installed()[$interaction['library']])) {
            $errors[] = "warning: {$w}: {$interaction['library']} is not reported as installed by the H5P service; the apply may fail until it is";
        }

        return $errors;
    }

    public function summary(array $lesson): string
    {
        $i = $lesson['interaction'] ?? null;

        return 'H5P' . (is_array($i) && ($i['kind'] ?? '') === 'h5p' ? ' · ' . H5pLibraries::summary((string) $i['library'], (array) $i['data']) : '');
    }
}
