<?php

namespace EscolaLms\Templates\Core;

use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Contracts\TemplateChannelContract;
use EscolaLms\Templates\Models\Template;

abstract class AbstractTemplateChannelClass implements TemplateChannelContract
{
    public static function sectionsRequired(): array
    {
        return static::sections()->where('required', true)->pluck('key')->toArray();
    }

    public static function sectionsReadonly(): array
    {
        return static::sections()->where('readonly', true)->pluck('key')->toArray();
    }

    public static function section(string $sectionKey): ?TemplateSectionSchema
    {
        return static::sections()->where('key', $sectionKey)->first();
    }

    public static function sectionExists(string $sectionKey): bool
    {
        return !is_null(static::section($sectionKey));
    }

    public static function processTemplateAfterSaving(Template $template): Template
    {
        return $template;
    }

    public static function channelAvailable(User $user): bool
    {
        return
            isset($user->notification_channels)
            && in_array(self::class, json_decode($user->notification_channels));
    }
}
