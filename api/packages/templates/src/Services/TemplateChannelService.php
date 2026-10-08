<?php

namespace EscolaLms\Templates\Services;

use EscolaLms\Templates\Contracts\TemplateChannelContract;
use EscolaLms\Templates\Core\TemplateSectionSchema;
use EscolaLms\Templates\Enums\TemplateSectionTypeEnum;
use EscolaLms\Templates\Services\Contracts\TemplateChannelServiceContract;
use Exception;
use InvalidArgumentException;

class TemplateChannelService implements TemplateChannelServiceContract
{
    protected static array $channels = [];

    protected array $sectionValidationErrors = [];

    public function register(string $class): void
    {
        if (!is_a($class, TemplateChannelContract::class, true)) {
            throw new InvalidArgumentException(__("Invalid argument. :expected expected, :given given.", ['expected' => TemplateChannelContract::class, 'given' => $class]));
        }

        if (in_array($class, self::$channels)) {
            return;
        }

        foreach ($class::sections() as $sectionSchema) {
            if (!$sectionSchema instanceof TemplateSectionSchema) {
                throw new Exception('Error in Template Channel Class in `sections()` method');
            }
        }
        foreach ($class::sectionsRequired() as $key) {
            if (!$class::sections()->where('key', $key)->first()) {
                throw new Exception('Error in Template Channel Class in `sectionsRequired()` method');
            }
        }

        self::$channels[] = $class;
    }

    public function list(): array
    {
        return self::$channels;
    }

    public function validateTemplateSections(string $class, array $sections): bool
    {
        $this->sectionValidationErrors = [];

        if (!in_array($class, self::$channels)) {
            $this->sectionValidationErrors[] = __('Channel not registered');
            return false;
        }
        foreach ($sections as $section => $content) {
            /** @var ?TemplateSectionSchema $sectionSchema */
            $sectionSchema = $class::section($section);
            if (is_null($sectionSchema)) {
                // skip sections not used by given Channel
                continue;
            }
            if (in_array($section, $class::sectionsRequired()) && empty($content)) {
                $this->sectionValidationErrors[] = __('Empty content for required section :section', ['section' => $section]);
                continue;
            }
            switch ($sectionSchema->getType()) {
                case TemplateSectionTypeEnum::SECTION_HTML():
                case TemplateSectionTypeEnum::SECTION_MJML():
                    if ($content === strip_tags($content)) {
                        $this->sectionValidationErrors[] = __(':section must contain HTML/MJML', ['section' => $section]);
                    }
                    break;
                case TemplateSectionTypeEnum::SECTION_TEXT():
                    if ($content !== strip_tags($content)) {
                        $this->sectionValidationErrors[] = __(':section must not contain HTML/MJML', ['section' => $section]);
                    }
                    break;
                case TemplateSectionTypeEnum::SECTION_URL():
                    if (!filter_var($content, FILTER_VALIDATE_URL)) {
                        $this->sectionValidationErrors[] = __(':section must be valid url', ['section' => $section]);
                    }
                    break;
                case TemplateSectionTypeEnum::SECTION_FABRIC();
                    if (!$this->isJson($content)) {
                        $this->sectionValidationErrors[] = __(':section must be valid JSON', ['section' => $section]);
                    }
                    break;
                default:
                    break;
            }
        }

        return empty($this->sectionValidationErrors);
    }

    public function lastValidationErrors(): array
    {
        return $this->sectionValidationErrors;
    }

    private function isJson(string $json): bool
    {
        return is_array(json_decode($json, true));
    }
}
