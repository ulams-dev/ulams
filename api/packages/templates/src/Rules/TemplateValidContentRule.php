<?php

namespace EscolaLms\Templates\Rules;

use EscolaLms\Templates\Facades\Template as FacadesTemplate;
use EscolaLms\Templates\Models\Template;
use EscolaLms\Templates\Services\Contracts\TemplateVariablesServiceContract;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\Request;

class TemplateValidContentRule implements Rule
{
    private ?Template $template;
    private Request $request;
    private TemplateVariablesServiceContract $templateVariableService;

    private array $missingSections = [];
    private array $readonlySections = [];
    private array $missingVariables = [];

    public function __construct(?Template $template = null)
    {
        $this->template = $template;
        $this->request = request();
        $this->templateVariableService = app(TemplateVariablesServiceContract::class);
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!$this->template || ($this->request->has('event') && $this->request->has('channel'))) {
            $templateVariableClass = FacadesTemplate::getVariableClassName($this->request->input('event'), $this->request->input('channel'));
            $channelClass = $this->request->input('channel');
        } else {
            $templateVariableClass = FacadesTemplate::getVariableClassName($this->template->event, $this->template->channel);
            $channelClass = $this->template->channel;
        }

        $allContent = '';
        $allSections = [];

        foreach ($value as $section) {
            $allSections[] = $section['key'];
            $allContent .= $section['content'] . ' ';
            if (!$this->templateVariableService->sectionIsValid($templateVariableClass, $section['key'], $section['content'])) {
                $this->missingVariables[$section['key']] = $this->templateVariableService->missingVariablesInSection($templateVariableClass, $section['key'], $section['content']);
            }
        }

        foreach ($channelClass::sectionsReadonly() as $section) {
            if (in_array($section, $allSections)) {
                $this->readonlySections[] = $section;
            }
        }

        foreach ($this->templateVariableService->requiredSectionsForChannel($templateVariableClass, $channelClass) as $section) {
            if (!in_array($section, $allSections)) {
                $this->missingSections[] = $section;
            }
        }

        return empty($this->missingVariables) && empty($this->missingSections) && empty($this->readonlySections) && $this->templateVariableService->contentIsValidForChannel($templateVariableClass, $channelClass, $allContent);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        $msg = '';
        foreach ($this->missingVariables as $section => $variables) {
            $msg .= __('Required variables in section: ' . $section . ' [' . implode(', ', $variables) . ']') . PHP_EOL;
        }
        foreach ($this->missingSections as $section) {
            $msg .= __('Required section: ' . $section) . PHP_EOL;
        }
        foreach ($this->readonlySections as $section) {
            $msg .= __('Readonly section:' . $section) . PHP_EOL;
        }
        return $msg;
    }
}
