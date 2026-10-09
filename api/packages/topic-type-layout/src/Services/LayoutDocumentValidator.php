<?php

namespace Ulams\TopicTypeLayout\Services;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Validates a layout document against the learner layout manifest (ADR 0052).
 *
 * A document is a list of nodes: `[{"component": "Timeline", "props": {...}, "id": "optional"}, ...]`.
 * Only the components named in the manifest are allowed, every node's props must match that
 * component's closed JSON Schema, and nothing else (no children, no unknown keys) is accepted. The
 * manifest is a copy of `front/ui/catalogue/learner-layout-manifest.json`, kept in sync by
 * `yarn workspace @ulams/ui learner-manifest` (CI runs it with `--check`).
 */
final class LayoutDocumentValidator
{
    public const MAX_NODES = 80;

    /** @var array<string, mixed>|null */
    private ?array $manifest = null;

    public function __construct(private readonly ?string $manifestPath = null)
    {
    }

    /** @return array<string, mixed> */
    public function manifest(): array
    {
        if ($this->manifest === null) {
            $path = $this->manifestPath ?? __DIR__ . '/../../resources/learner-layout-manifest.json';
            $decoded = json_decode((string) file_get_contents($path), true);
            $this->manifest = is_array($decoded) ? $decoded : ['components' => []];
        }

        return $this->manifest;
    }

    /** @return string[] the approved component names */
    public function components(): array
    {
        return array_keys($this->manifest()['components'] ?? []);
    }

    /** @return string[] readable errors ("/2/props/items: ..."); empty when the document is valid */
    public function validate(mixed $document, int $maxErrors = 20): array
    {
        if (!is_array($document) || $document === [] || !array_is_list($document)) {
            return ['/: the document must be a non-empty list of {component, props} nodes'];
        }
        if (count($document) > self::MAX_NODES) {
            return ['/: a layout holds at most ' . self::MAX_NODES . ' nodes'];
        }

        $components = $this->manifest()['components'] ?? [];
        $errors = [];
        foreach ($document as $index => $node) {
            $at = '/' . $index;
            if (!is_array($node) || array_is_list($node)) {
                $errors[] = "{$at}: a node must be an object with a component and props";
                continue;
            }
            $unknown = array_diff(array_keys($node), ['component', 'props', 'id']);
            if ($unknown !== []) {
                $errors[] = "{$at}: unknown key " . implode(', ', array_map('strval', $unknown)) . ' (only component, props and id are allowed)';
            }
            if (isset($node['id']) && (!is_string($node['id']) || !preg_match('/^[A-Za-z0-9_-]{1,40}$/', $node['id']))) {
                $errors[] = "{$at}/id: letters, digits, dashes and underscores, at most 40 characters";
            }
            $name = $node['component'] ?? null;
            if (!is_string($name) || !isset($components[$name])) {
                $errors[] = "{$at}/component: " . (is_string($name) ? "'{$name}'" : 'missing') . ' is not an approved layout component (allowed: ' . implode(', ', array_keys($components)) . ')';
                continue;
            }
            $props = $node['props'] ?? [];
            if (!is_array($props) || ($props !== [] && array_is_list($props))) {
                $errors[] = "{$at}/props: must be an object";
                continue;
            }
            foreach ($this->validateProps($props, $components[$name]['props'] ?? [], $maxErrors) as $line) {
                $errors[] = "{$at}/props" . $line;
            }
            if (count($errors) >= $maxErrors) {
                break;
            }
        }

        return array_slice(array_values(array_unique($errors)), 0, $maxErrors);
    }

    /**
     * @param array<string, mixed> $props
     * @param array<string, mixed> $schema
     * @return string[]
     */
    private function validateProps(array $props, array $schema, int $maxErrors): array
    {
        $validator = new Validator();
        $validator->setMaxErrors($maxErrors);
        $validator->setStopAtFirstError(false);
        $validator->parser()->setOption('defaultDraft', '2020-12');
        // the catalogue's own `href` format: relative, fragment, http(s), mailto and tel links only,
        // never javascript: or data: (same rule as isSafeHref in front/ui/src/schema.ts)
        $validator->parser()->getFormatResolver()->registerCallable('string', 'href', self::isSafeHref(...));
        $result = $validator->validate($this->toObject($props === [] ? new \stdClass() : $props), $this->toObject($this->withoutDefaults($schema)));
        if ($result->isValid()) {
            return [];
        }
        $lines = [];
        foreach ((new ErrorFormatter())->format($result->error(), true) as $path => $messages) {
            foreach ((array) $messages as $message) {
                $lines[] = ($path === '/' ? '' : $path) . ': ' . $message;
            }
        }

        return $lines;
    }

    /**
     * Drops `default` keywords. opis fills a missing property from its default before it checks
     * `required`, so a document without a required prop would pass; the learner renderer applies the
     * defaults itself, and an author has to write what the component needs.
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function withoutDefaults(array $schema): array
    {
        $out = [];
        foreach ($schema as $key => $value) {
            if ($key === 'default') {
                continue;
            }
            if ($key === 'properties' && is_array($value)) {
                $value = array_map(fn ($sub) => is_array($sub) ? $this->withoutDefaults($sub) : $sub, $value);
            } elseif ($key === 'items' && is_array($value) && !array_is_list($value)) {
                $value = $this->withoutDefaults($value);
            } elseif (in_array($key, ['anyOf', 'oneOf', 'allOf', 'prefixItems'], true) && is_array($value)) {
                $value = array_map(fn ($sub) => is_array($sub) ? $this->withoutDefaults($sub) : $sub, $value);
            } elseif (in_array($key, ['additionalProperties', 'not', 'if', 'then', 'else'], true) && is_array($value)) {
                $value = $this->withoutDefaults($value);
            }
            $out[$key] = $value;
        }

        return $out;
    }

    public static function isSafeHref(string $value): bool
    {
        $v = trim($value);
        if ($v === '') {
            return false;
        }
        if (str_starts_with($v, '/') && !str_starts_with($v, '//')) {
            return true;
        }
        if (str_starts_with($v, '#') || str_starts_with($v, '?') || str_starts_with($v, './')) {
            return true;
        }

        return preg_match('#^(https?://|mailto:|tel:)#i', $v) === 1;
    }

    /** Arrays become objects when they are maps; lists stay lists (opis validates decoded JSON). */
    private function toObject(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn ($v) => $this->toObject($v), $value);
        }
        $object = new \stdClass();
        foreach ($value as $key => $v) {
            $object->{$key} = $this->toObject($v);
        }

        return $object;
    }
}
