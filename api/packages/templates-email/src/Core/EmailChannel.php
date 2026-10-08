<?php

namespace Ulams\TemplatesEmail\Core;

use Ulams\Core\Models\User;
use Ulams\Templates\Contracts\TemplateChannelContract;
use Ulams\Templates\Core\AbstractTemplateChannelClass;
use Ulams\Templates\Core\SettingsVariables;
use Ulams\Templates\Core\TemplateSectionSchema;
use Ulams\Templates\Enums\TemplateSectionTypeEnum;
use Ulams\Templates\Events\EventWrapper;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Models\TemplateSection;
use Ulams\TemplatesEmail\Services\Contracts\MjmlServiceContract;
use HTMLPurifier_Config;
use HTMLPurifier;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailChannel extends AbstractTemplateChannelClass implements TemplateChannelContract
{
    public static function send(EventWrapper $event, array $sections): bool
    {
        if (!Arr::has($sections, self::sectionsRequired()) || !array_key_exists('contentHtml', $sections)) {
            return false;
        }
        $email = $event->getUser()->email;
        if (method_exists($event, 'getEmail')) {
            $email = $event->getEmail() ?? $email;
        }
        $mailable = new EmailMailable();
        $mailable->to($email);
        $mailable->subject($sections['title']);
        $mailable->html($sections['contentHtml']);
        Mail::send($mailable);

        return true;
    }

    public static function preview(User $user, array $sections): bool
    {
        if (!Arr::has($sections, self::sectionsRequired()) || !array_key_exists('contentHtml', $sections)) {
            Log::error('Missing email sections in preview', $sections);
            return false;
        }

        $mailable = new EmailMailable();
        $mailable->to($user->email);
        $mailable->subject($sections['title']);
        $mailable->html($sections['contentHtml']);

        Mail::send($mailable);

        return true;
    }

    private static function renderMjml(string $mjml): string
    {
        return app(MjmlServiceContract::class)->render($mjml);
    }

    private static function fixHtml(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);
        $purifier = new HTMLPurifier($config);
        return $purifier->purify($html);
    }

    public static function sections(): Collection
    {
        return new Collection([
            // @phpstan-ignore-next-line
            new TemplateSectionSchema('title', TemplateSectionTypeEnum::SECTION_TEXT(), true),
            // @phpstan-ignore-next-line
            new TemplateSectionSchema('content', TemplateSectionTypeEnum::SECTION_MJML(), true),
            // @phpstan-ignore-next-line
            new TemplateSectionSchema('contentHtml', TemplateSectionTypeEnum::SECTION_HTML(), false, true),
        ]);
    }

    public static function processTemplateAfterSaving(Template $template): Template
    {
        $content = $template->sections()->where('key', 'content')->first()->content;

        foreach (SettingsVariables::settings() as $key => $variable) {
            $content = Str::replace($key, $variable['value'], $content);
        }

        if (Str::contains($content, '<mjml>')) {
            $contentHtml = self::renderMjml($content);
        } else {
            $contentHtml = self::fixHtml($content);
            TemplateSection::updateOrCreate(['template_id' => $template->getKey(), 'key' => 'content'], ['content' => $content]);
        }
        TemplateSection::updateOrCreate(['template_id' => $template->getKey(), 'key' => 'contentHtml'], ['content' => $contentHtml]);

        return $template->refresh();
    }
}
