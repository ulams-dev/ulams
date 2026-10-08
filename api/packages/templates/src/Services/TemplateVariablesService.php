<?php

namespace EscolaLms\Templates\Services;

use EscolaLms\Templates\Contracts\TemplateChannelContract;
use EscolaLms\Templates\Contracts\TemplateVariableContract;
use EscolaLms\Templates\Core\TemplateSectionSchema;
use EscolaLms\Templates\Services\Contracts\TemplateChannelServiceContract;
use EscolaLms\Templates\Services\Contracts\TemplateVariablesServiceContract;
use Exception;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TemplateVariablesService implements TemplateVariablesServiceContract
{
    protected static array $variables = [];

    protected TemplateChannelServiceContract $templateChannelService;

    public function __construct(TemplateChannelService $templateChannelService)
    {
        $this->templateChannelService = $templateChannelService;
    }

    public function registerForChannel(string $variableClass, string $channelClass): void
    {
        if (!is_a($variableClass, TemplateVariableContract::class, true)) {
            throw new InvalidArgumentException(__("Invalid argument. :expected expected, :given given.", ['expected' => TemplateVariableContract::class, 'given' => $variableClass]));
        }

        $this->templateChannelService->register($channelClass);

        if (array_key_exists($channelClass, self::$variables) && in_array($variableClass, self::$variables[$channelClass])) {
            return;
        }

        if (!$this->validForChannel($variableClass, $channelClass)) {
            throw new Exception(__('Variable class :variable can not be used for channel :channel.', ['variable' => $variableClass, 'channel' => $channelClass]));
        }

        self::$variables[$channelClass][] = $variableClass;
    }

    private function validForChannel(string $variableClass, string $channelClass): bool
    {
        foreach ($variableClass::requiredSections() as $section) {
            if (!$channelClass::sectionExists($section)) {
                // variable class can not require a section that does not exist in channel class
                return false;
            }
        }
        foreach ($channelClass::sectionsRequired() as $section) {
            if (!array_key_exists($section, $variableClass::defaultSectionsContent())) {
                // variable class must declare default content for all required channel class sections
                return false;
            }
        }
        return true;
    }

    public function sectionIsValid(string $variableClass, string $section, string $content): bool
    {
        if (!is_a($variableClass, TemplateVariableContract::class, true)) {
            return false;
        }

        return collect($variableClass::requiredVariablesInSection($section))
            ->every(fn ($variable) => collect(self::convertVarNameToAllFormats($variable))
                ->contains(fn ($var) => Str::contains($content, $var)));
    }

    public static function convertVarNameToAllFormats(string $varname): array
    {
        return [$varname,  '$' . "{" . str_replace("@", "", $varname) . "}"];
    }

    public function missingVariablesInSection(string $variableClass, string $section, string $content): array
    {
        if (!is_a($variableClass, TemplateVariableContract::class, true)) {
            return [];
        }

        return array_filter(
            $variableClass::requiredVariablesInSection($section),
            fn ($variable) =>
            !collect(self::convertVarNameToAllFormats($variable))->contains(fn ($var) => Str::contains($content, $var))
        );
    }

    public function contentIsValidForChannel(string $variableClass, string $channelClass, string $content): bool
    {
        if (!is_a($variableClass, TemplateVariableContract::class, true)) {
            return false;
        }
        if (!is_a($channelClass, TemplateChannelContract::class, true)) {
            return false;
        }

        return collect($this->requiredVariablesForChannel($variableClass, $channelClass))
            ->every(fn ($variable) => collect(self::convertVarNameToAllFormats($variable))
                ->contains(fn ($var) => Str::contains($content, $var)));

        // return Str::containsAll($content, $this->requiredVariablesForChannel($variableClass, $channelClass));
    }

    public function requiredSectionsForChannel(string $variableClass, string $channelClass): array
    {
        if (!is_a($variableClass, TemplateVariableContract::class, true)) {
            return [];
        }
        if (!is_a($channelClass, TemplateChannelContract::class, true)) {
            return [];
        }
        return array_unique(array_merge($channelClass::sectionsRequired(), $variableClass::requiredSections()));
    }

    public function requiredVariablesForChannel(string $variableClass, string $channelClass): array
    {
        if (!is_a($variableClass, TemplateVariableContract::class, true)) {
            return [];
        }
        if (!is_a($channelClass, TemplateChannelContract::class, true)) {
            return [];
        }

        $variables = $variableClass::requiredVariables();
        foreach ($channelClass::sections() as $sectionSchema) {
            /** @var TemplateSectionSchema $sectionSchema */
            $variables = array_merge($variables, $variableClass::requiredVariablesInSection($sectionSchema->getKey()));
        }
        return array_unique($variables);
    }

    public function listForChannel(string $class): array
    {
        return self::$variables[$class] ?? [];
    }
}
