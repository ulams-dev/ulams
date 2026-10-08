<?php

namespace Ulams\TemplatesPdf\Core;

use Ulams\Core\Models\User;
use Ulams\Templates\Contracts\TemplateChannelContract;
use Ulams\Templates\Core\AbstractTemplateChannelClass;
use Ulams\Templates\Core\SettingsVariables;
use Ulams\Templates\Core\TemplateSectionSchema;
use Ulams\Templates\Enums\TemplateSectionTypeEnum;
use Ulams\Templates\Events\EventWrapper;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Models\TemplateSection;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Illuminate\Support\Collection;
use Ulams\Templates\Facades\Template as TemplateFacade;
use ReflectionClass;
use ReflectionProperty;

class PdfChannel extends AbstractTemplateChannelClass implements TemplateChannelContract
{
    public static function send(EventWrapper $event, array $sections): bool
    {
        $varsService = TemplateFacade::getVariableClassName($event->eventClass(), PdfChannel::class);
        $vars = array_merge(SettingsVariables::getSettingsValues(), $varsService::variablesFromEvent($event));

        FabricPDF::create([
            'user_id' => $event->user()->id,
            'template_id' => $sections['template_id'],
            'title' => $sections['title'],
            'content' => $sections['content'],
            'vars' => $vars,
            'assignable_type' => $varsService::assignableClass(),
            'assignable_id' => $varsService::assignableClass() ? $event->extractIdForPropertyOfClass($varsService::assignableClass()) : null,
        ]);

        return true;
    }

    public static function preview(User $user, array $sections): bool
    {
        return true;
    }

    public static function sections(): Collection
    {
        return new Collection([
            new TemplateSectionSchema('title', TemplateSectionTypeEnum::SECTION_TEXT(), true),
            new TemplateSectionSchema('content', TemplateSectionTypeEnum::SECTION_FABRIC(), true),
        ]);
    }

    public static function processTemplateAfterSaving(Template $template): Template
    {
        $content = $template->sections()->where('key', 'content')->first()->content;

        TemplateSection::updateOrCreate(['template_id' => $template->getKey(), 'key' => 'content'], ['content' => $content]);

        return $template->refresh();
    }
}
