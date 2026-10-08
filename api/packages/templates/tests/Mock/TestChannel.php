<?php

namespace Ulams\Templates\Tests\Mock;

use Ulams\Core\Models\User;
use Ulams\Templates\Contracts\TemplateChannelContract;
use Ulams\Templates\Core\AbstractTemplateChannelClass;
use Ulams\Templates\Core\TemplateSectionSchema;
use Ulams\Templates\Enums\TemplateSectionTypeEnum;
use Ulams\Templates\Events\EventWrapper;
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
