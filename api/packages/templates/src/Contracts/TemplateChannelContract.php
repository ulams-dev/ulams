<?php

namespace Ulams\Templates\Contracts;

use Ulams\Core\Models\User;
use Ulams\Templates\Core\TemplateSectionSchema;
use Ulams\Templates\Events\EventWrapper;
use Ulams\Templates\Models\Template;
use Illuminate\Support\Collection;

interface TemplateChannelContract
{
    public static function send(EventWrapper $event, array $sections): bool;
    public static function preview(User $user, array $sections): bool;

    public static function sections(): Collection;
    public static function sectionsRequired(): array;
    public static function sectionsReadonly(): array;

    public static function section(string $sectionKey): ?TemplateSectionSchema;
    public static function sectionExists(string $sectionKey): bool;

    public static function processTemplateAfterSaving(Template $template): Template;

    public static function channelAvailable(User $user): bool;
}
