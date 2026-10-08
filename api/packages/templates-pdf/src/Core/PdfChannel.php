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
use Illuminate\Support\Facades\Log;
use Throwable;
use Ulams\TemplatesPdf\Services\Contracts\PdfGeneratorContract;
use Ulams\TemplatesPdf\UlamsTemplatesPdfServiceProvider;

class PdfChannel extends AbstractTemplateChannelClass implements TemplateChannelContract
{
    public static function send(EventWrapper $event, array $sections): bool
    {
        $varsService = TemplateFacade::getVariableClassName($event->eventClass(), PdfChannel::class);
        $vars = array_merge(SettingsVariables::getSettingsValues(), $varsService::variablesFromEvent($event));

        // Store the template as designed (not the variable-substituted copy):
        // the renderer fills the fields from `vars`, so values never have to be
        // spliced into the JSON.
        $template = Template::with('sections')->find($sections['template_id']);
        $content = optional($template?->sections->firstWhere('key', 'content'))->content ?? $sections['content'];

        $pdf = FabricPDF::create([
            'user_id' => $event->user()->id,
            'template_id' => $sections['template_id'],
            'title' => $sections['title'],
            'content' => $content,
            'vars' => $vars,
            'certificate_id' => $vars[PdfVariables::VAR_CERTIFICATE_ID] ?? null,
            'assignable_type' => $varsService::assignableClass(),
            'assignable_id' => $varsService::assignableClass() ? $event->extractIdForPropertyOfClass($varsService::assignableClass()) : null,
        ]);

        if (config(UlamsTemplatesPdfServiceProvider::CONFIG_KEY . '.storage.render_on_create', true)) {
            try {
                app(PdfGeneratorContract::class)->store($pdf);
            } catch (Throwable $e) {
                // The record exists either way; the PDF is rendered on download.
                Log::warning('PDF could not be rendered when it was issued', ['pdf_id' => $pdf->getKey(), 'error' => $e->getMessage()]);
            }
        }

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
