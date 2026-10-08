<?php

namespace EscolaLms\Templates\Tests\Mock;

use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Contracts\TemplateChannelContract;
use EscolaLms\Templates\Core\AbstractTemplateChannelClass;
use EscolaLms\Templates\Core\TemplateSectionSchema;
use EscolaLms\Templates\Enums\TemplateSectionTypeEnum;
use EscolaLms\Templates\Events\EventWrapper;
use Illuminate\Database\Eloquent\Collection;

class TestChannel extends AbstractTemplateChannelClass implements TemplateChannelContract
{
    public static array $handledNotifications = [];

    public static function send(EventWrapper $event, array $sections): bool
    {
        self::$handledNotifications[] = [
            'event' => $event,
            'title' => $sections['title'],
            'content' => $sections['content'],
            'url' => $sections['url'] ?? null,
        ];
        return true;
    }

    public static function preview(User $user, array $sections): bool
    {
        self::$handledNotifications[] = [
            'event' => 'preview',
            'title' => $sections['title'],
            'content' => $sections['content'],
            'url' => $sections['url'] ?? null,
        ];
        return true;
    }

    public static function sections(): Collection
    {
        return new Collection([
            new TemplateSectionSchema('title', TemplateSectionTypeEnum::SECTION_TEXT(), true),
            new TemplateSectionSchema('content', TemplateSectionTypeEnum::SECTION_HTML(), true),
            new TemplateSectionSchema('url', TemplateSectionTypeEnum::SECTION_URL()),
        ]);
    }
}
