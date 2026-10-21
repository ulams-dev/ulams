<?php

namespace Ulams\TemplatesEmail\Core;

use Ulams\Core\Models\User;
use Ulams\Templates\Contracts\TemplateVariableContract;
use Ulams\Templates\Core\AbstractTemplateVariableClass;
use Ulams\Templates\Core\SettingsVariables;
use Ulams\Templates\Events\EventWrapper;
use Ulams\TemplatesEmail\UlamsTemplatesEmailServiceProvider;
use Illuminate\Support\Str;

abstract class EmailVariables extends AbstractTemplateVariableClass implements TemplateVariableContract
{
    const VAR_APP_NAME     = '@VarAppName';

    public static function mockedVariables(?User $user = null): array
    {
        return array_merge(SettingsVariables::getHtmlSettingsValues(), [
            self::VAR_APP_NAME => config('app.name')
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(SettingsVariables::getHtmlSettingsValues(), [
            self::VAR_APP_NAME => config('app.name')
        ]);
    }

    public static function requiredSections(): array
    {
        return [];
    }

    public static function wrapWithMjml(string $content = ''): string
    {
        if (!Str::contains($content, ['<mj-text'])) {
            $content = '<mj-text>' . $content . '</mj-text>';
        }

        $template = config(
            UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.default_template',
            <<<MJML_TEMPLATE
            <mjml>
            <mj-body>
                <mj-section background-color="white">
                    <mj-column>
                        @VarTemplateContent
                    </mj-column>
                </mj-section>
            </mj-body>
            </mjml>
            MJML_TEMPLATE
        );

        return self::withLanguage(str_replace('@VarTemplateContent', $content, $template));
    }

    /** Languages written right to left: they get `dir="rtl"` on the generated default templates. */
    private const RTL_LANGUAGES = ['ar', 'fa', 'he', 'ur'];

    /**
     * Gives an `<mjml>` root without `lang` the language of the application and its text direction,
     * so the e-mail is announced in the right voice by screen readers (WCAG 3.1.1). A root that
     * already sets `lang` (a template an operator wrote or configured) is left alone.
     */
    public static function withLanguage(string $mjml, ?string $locale = null): string
    {
        $language = strtolower(substr((string) ($locale ?? app()->getLocale()), 0, 2));
        if (!preg_match('/^[a-z]{2}$/', $language)) {
            return $mjml;
        }
        $direction = in_array($language, self::RTL_LANGUAGES, true) ? 'rtl' : 'ltr';

        return preg_replace_callback(
            '/<mjml(\s[^>]*)?>/i',
            static function (array $match) use ($language, $direction): string {
                $attributes = $match[1] ?? '';
                if (preg_match('/\slang\s*=/i', $attributes)) {
                    return $match[0];
                }

                return '<mjml' . $attributes . ' lang="' . $language . '" dir="' . $direction . '">';
            },
            $mjml,
            1
        ) ?? $mjml;
    }
}
