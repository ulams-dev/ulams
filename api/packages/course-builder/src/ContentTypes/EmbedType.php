<?php

namespace Ulams\CourseBuilder\ContentTypes;

use Ulams\TopicTypes\Models\TopicContent\OEmbed;

/**
 * An external page or video shown inside the lesson (an oEmbed topic): the address and a title, nothing to upload. A lesson that
 * points at an existing topic (`assetRef`, set by adoption) is applied by reference and never reaches `topics()`.
 */
final class EmbedType implements ContentType
{
    public function key(): string
    {
        return 'embed';
    }

    public function label(): string
    {
        return 'Embedded page or video';
    }

    public function description(): string
    {
        return 'A page or video from another site, shown inside the lesson.';
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
        $embed = (array) ($lesson['embed'] ?? []);

        return [['slot' => 'primary', 'element' => $lesson['id'], 'class' => OEmbed::class, 'data' => [
            'title' => $lesson['title'],
            'summary' => $lesson['summary'] ?? null,
            'duration' => $lesson['minutes'] . ' min',
            'value' => (string) ($embed['url'] ?? ''),
        ]]];
    }

    public function check(array $lesson, string $where, array $known): array
    {
        $url = (string) ($lesson['embed']['url'] ?? '');
        if ($url === '' || !preg_match('#^https://[^\s<>"\']+$#i', $url)) {
            return ["{$where}: an embedded lesson needs a https address"];
        }

        return [];
    }

    public function summary(array $lesson): string
    {
        return 'Embedded · ' . (string) (parse_url((string) ($lesson['embed']['url'] ?? ''), PHP_URL_HOST) ?: 'external page');
    }
}
