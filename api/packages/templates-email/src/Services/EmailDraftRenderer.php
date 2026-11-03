<?php

namespace Ulams\TemplatesEmail\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Ulams\Core\Models\User;
use Ulams\Templates\Core\SettingsVariables;
use Ulams\Templates\Facades\Template as FacadesTemplate;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Models\TemplateSection;
use Ulams\Templates\Services\Contracts\TemplateServiceContract;
use Ulams\Templates\Services\TemplateVariablesService;
use Ulams\TemplatesEmail\Accessibility\EmailA11yChecker;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Services\Contracts\MjmlServiceContract;

/**
 * Renders an UNSAVED e-mail template draft with the sample values of its event, the same way a saved
 * template is pre-rendered on save and filled on send. Nothing is stored and nothing is sent.
 */
class EmailDraftRenderer
{
    public function __construct(
        private MjmlServiceContract $mjml,
        private TemplateServiceContract $templates,
        private EmailA11yChecker $a11y
    ) {
    }

    /**
     * @param array<string, string> $sampleOverrides variable name => sample value (plain text, escaped like any value)
     * @param string|null $locale language the draft is written for: the sample values and `lang` are rendered in it
     * @return array{title: string, html: string, errors: string[], missing_variables: string[], warnings: array, locale: string|null}
     */
    public function render(User $user, string $event, string $title, string $content, array $sampleOverrides = [], ?string $locale = null): array
    {
        $previous = App::getLocale();
        if ($locale !== null) {
            App::setLocale($locale);
        }
        try {
            return $this->renderDraft($user, $event, $title, $content, $sampleOverrides, $locale);
        } finally {
            App::setLocale($previous);
        }
    }

    private function renderDraft(User $user, string $event, string $title, string $content, array $sampleOverrides, ?string $locale): array
    {
        $variableClass = FacadesTemplate::getVariableClassName($event, EmailChannel::class);

        // Same steps as EmailChannel::processTemplateAfterSaving.
        foreach (SettingsVariables::settings() as $key => $variable) {
            $content = Str::replace($key, $variable['value'], $content);
        }
        if (EmailChannel::isMjml($content)) {
            $rendered = $this->mjml->renderWithErrors($content);
            $contentHtml = $rendered['html'];
            $errors = $rendered['errors'];
        } else {
            $contentHtml = EmailChannel::fixHtml($content);
            $errors = [];
        }

        $template = new Template(['event' => $event, 'channel' => EmailChannel::class]);
        $template->setRelation('sections', new Collection([
            new TemplateSection(['key' => 'title', 'content' => $title]),
            new TemplateSection(['key' => 'contentHtml', 'content' => $contentHtml]),
        ]));
        $sections = $this->templates->generateContentUsingVariables(
            $template,
            array_merge($variableClass::mockedVariables($user), $sampleOverrides)
        );

        $missing = $this->missingVariables($variableClass, $title, $content);

        return [
            'title' => $sections['title'],
            'html' => $sections['contentHtml'],
            'errors' => $errors,
            'missing_variables' => $missing,
            'warnings' => $this->a11y->check($sections['contentHtml']),
            'locale' => $locale,
        ];
    }

    /**
     * Variables the event requires (overall or in the title or content) that neither the title nor the
     * source of the draft uses, in either `@VarName` or `${VarName}` form.
     *
     * @return string[]
     */
    private function missingVariables(string $variableClass, string $title, string $content): array
    {
        $required = array_merge(
            $variableClass::requiredVariables(),
            $variableClass::requiredVariablesInSection('title'),
            $variableClass::requiredVariablesInSection('content')
        );
        $text = $title . "\n" . $content;

        return array_values(array_filter(
            array_unique($required),
            fn (string $variable) => !Str::contains($text, TemplateVariablesService::convertVarNameToAllFormats($variable))
        ));
    }

    /**
     * Names a sample override may set: the variables of the event and its sample values.
     *
     * @return string[]
     */
    public function overridableVariables(string $event, ?User $user = null): array
    {
        $variableClass = FacadesTemplate::getVariableClassName($event, EmailChannel::class);

        return array_values(array_unique(array_merge(
            $variableClass::variables(),
            array_keys($variableClass::mockedVariables($user))
        )));
    }
}
