<?php

namespace Ulams\CourseBuilder\Ui;

use Illuminate\Support\Facades\Log;
use Ulams\Ai\Services\JsonSchemaValidator;

/**
 * Server-side check of UI the model chose ("render_ui validated server-side", spec 2.7): the
 * component must be in the `@ulams/ui` builder catalogue manifest (copied into this package by
 * `yarn workspace @ulams/ui builder-manifest`) and its props must match the component's schema.
 * Unknown or invalid choices fall back to plain text and are logged.
 */
final class UiCatalogue
{
    /** @var array<string,mixed>|null */
    private ?array $manifest = null;

    public function __construct(private readonly JsonSchemaValidator $validator)
    {
    }

    public static function manifestPath(): string
    {
        return __DIR__ . '/../../resources/catalogue/manifest.json';
    }

    /** @return array<string,mixed> */
    public function manifest(): array
    {
        return $this->manifest ??= (array) json_decode((string) file_get_contents(self::manifestPath()), true);
    }

    public function catalogId(): string
    {
        return (string) ($this->manifest()['catalogId'] ?? '');
    }

    public function has(string $component): bool
    {
        return isset($this->manifest()['components'][$component]);
    }

    /** @return string[] components the model may pick for a purpose (`interview`, `chat-reply`) */
    public function selectable(string $purpose): array
    {
        return array_keys(array_filter(
            (array) ($this->manifest()['components'] ?? []),
            fn (array $c) => ($c['modelSelectable'] ?? false) === $purpose,
        ));
    }

    /**
     * @param array<string,mixed> $props
     * @return string[] errors; empty when the component exists and the props are valid
     */
    public function validate(string $component, array $props): array
    {
        if (!$this->has($component)) {
            return ["Unknown component {$component}"];
        }

        return $this->validator->validate($props, (array) $this->manifest()['components'][$component]['props']);
    }

    /**
     * The component node to stream, or a Text node with the fallback when the choice is invalid.
     *
     * @param array<string,mixed> $props
     * @return array<string,mixed> a flat A2UI component (`id`, `component`, props)
     */
    public function nodeOrFallback(string $id, string $component, array $props, string $fallbackText, ?string $purpose = null): array
    {
        $errors = $this->validate($component, $props);
        if ($errors === [] && $purpose !== null && !in_array($component, $this->selectable($purpose), true)) {
            $errors = ["{$component} cannot be chosen for {$purpose}"];
        }
        if ($errors !== []) {
            Log::info('course-builder: UI choice replaced by text fallback', ['component' => $component, 'errors' => array_slice($errors, 0, 5)]);

            return ['id' => $id, 'component' => 'Text', 'text' => $fallbackText];
        }

        return ['id' => $id, 'component' => $component] + $props;
    }
}
