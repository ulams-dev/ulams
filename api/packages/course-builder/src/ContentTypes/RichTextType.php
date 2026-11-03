<?php

namespace Ulams\CourseBuilder\ContentTypes;

use Ulams\CourseBuilder\Apply\LessonMarkdown;
use Ulams\TopicTypes\Models\TopicContent\RichText;

final class RichTextType implements ContentType
{
    public function key(): string
    {
        return 'richtext';
    }

    public function label(): string
    {
        return 'Rich text';
    }

    public function description(): string
    {
        return 'Formatted text with a list of the sections it draws on.';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function followUp(): ?string
    {
        return null;
    }

    public function topics(array $lesson, array $labels, string $sourceTitle, string $language = 'en'): array
    {
        return [['slot' => 'primary', 'element' => $lesson['id'], 'class' => RichText::class, 'data' => [
            'title' => $lesson['title'],
            'summary' => $lesson['summary'] ?? null,
            'duration' => $lesson['minutes'] . ' min',
            'value' => LessonMarkdown::render($lesson, $labels, $sourceTitle),
        ]]];
    }

    public function check(array $lesson, string $where, array $known): array
    {
        return [];
    }

    public function summary(array $lesson): string
    {
        return 'Rich text';
    }
}
