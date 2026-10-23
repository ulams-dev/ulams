<?php

namespace Ulams\CourseBuilder\StyleProfile;

use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Dto\LlmRequest;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\Ai\Prompts\PromptRegistry;
use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\StyleProfile;
use Ulams\CourseBuilder\Pipeline\Llm;
use Ulams\CourseBuilder\Pipeline\PromptContext;
use Ulams\Settings\Support\ThemePresets;

/**
 * One LLM call (`style_profile_extract`, model from config, logged in `ai_calls` with model, tokens and cost) that
 * reads the style guide fragments of a holder session into a proposal, then the sanitiser (ADR 0196). The style
 * guide is untrusted input: it travels only inside the escaped `<source_document untrusted="true">` block of the
 * user turn, the model has no tools, and whatever it returns passes through the sanitiser, so an instruction in
 * the guide can at most produce a value that is dropped and reported.
 */
final class StyleProfileExtractor
{
    public function __construct(
        private readonly LlmClient $client,
        private readonly PromptRegistry $prompts,
        private readonly SchemaRegistry $schemas,
        private readonly PromptContext $context,
        private readonly StyleProfileCatalogue $catalogue,
    ) {
    }

    public function enabled(): bool
    {
        return $this->client->enabled();
    }

    /**
     * @return array{profile:array<string,mixed>,warnings:string[]}
     * @throws LlmException
     */
    public function extract(Session $holder, string $profileId, ?int $userId): array
    {
        $components = $this->catalogue->components();
        $result = $this->client->generate(new LlmRequest(
            task: 'style_profile_extract',
            prompt: $this->prompts->get(Llm::PROMPTS, 'style_profile_extract'),
            blocks: [
                $this->context->sourceBlock($holder),
                $this->context->instruction('Read the style guide into the style profile schema.', ['components' => $components, 'themePresets' => ThemePresets::names()]),
            ],
            schema: $this->schemas->get('outputs/style_profile_extract'),
            subject: ['type' => StyleProfile::SUBJECT_TYPE, 'id' => $profileId],
            userId: $userId,
        ));

        $fragments = $this->context->knownFragments($holder);
        $sanitized = (new StyleProfileSanitizer($fragments, $components, true))->sanitize(self::fromModel($result->data));
        // `blocking` is the author's decision, whatever the model wrote
        if (isset($sanitized['profile']['banned'])) {
            $sanitized['profile']['banned']['blocking'] = false;
        }

        return $sanitized;
    }

    /**
     * The extraction schema has no optional keys (the provider needs every key), so "the guide says nothing" arrives
     * as an empty string, 0 or `unspecified`. Those become absent values here.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function fromModel(array $data): array
    {
        $clean = static function (mixed $value) use (&$clean): mixed {
            if (is_array($value)) {
                $out = [];
                foreach ($value as $key => $item) {
                    $item = $clean($item);
                    if ($item === '' || $item === 0 || $item === 'unspecified' || $item === null) {
                        continue;
                    }
                    $out[$key] = $item;
                }

                return array_is_list($value) ? array_values($out) : $out;
            }

            return $value;
        };

        // yes / no / unspecified are the model's spelling of true / false / absent, for the imagery rules only
        foreach (['altTextRequired', 'decorativeAllowed', 'noTextInImages'] as $rule) {
            if (is_array($data['imagery'] ?? null) && isset($data['imagery'][$rule])) {
                $data['imagery'][$rule] = match ($data['imagery'][$rule]) {
                    'yes' => true,
                    'no' => false,
                    default => '',
                };
            }
        }

        return (array) $clean($data);
    }
}
