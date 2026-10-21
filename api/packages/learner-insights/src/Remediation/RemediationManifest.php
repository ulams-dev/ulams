<?php

namespace Ulams\LearnerInsights\Remediation;

use Ulams\TopicTypeLayout\Services\LayoutDocumentValidator;

/**
 * The blocks a remediation may hold, as the learner UI can render them: Prose, WorkedExample,
 * PracticeQuestion and ElaborativeQuestion, each with a closed props schema. The manifest is a copy of
 * `front/ui/catalogue/remediation-manifest.json`, kept in sync by `yarn workspace @ulams/ui remediation-manifest`
 * (CI runs it with `--check`). It reuses the layout validator of ADR 0052, which checks nodes against closed
 * JSON Schemas and the catalogue's own `href` format.
 */
final class RemediationManifest
{
    private readonly LayoutDocumentValidator $validator;

    public function __construct(?string $manifestPath = null)
    {
        $this->validator = new LayoutDocumentValidator($manifestPath ?? __DIR__ . '/../../resources/remediation-manifest.json');
    }

    /** @return string[] the approved block names */
    public function components(): array
    {
        return $this->validator->components();
    }

    /**
     * @param array<int,array{component:string,props:array<string,mixed>}> $blocks as stored in `document.blocks`
     * @return string[] readable errors ("/1/props/practice/options: ..."); empty when every block is valid
     */
    public function validate(array $blocks): array
    {
        return $this->validator->validate(array_map(fn (array $b) => ['component' => $b['component'], 'props' => $b['props']], array_values($blocks)));
    }
}
