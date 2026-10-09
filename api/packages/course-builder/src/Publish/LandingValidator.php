<?php

namespace Ulams\CourseBuilder\Publish;

use Ulams\Ai\Services\JsonSchemaValidator;

/**
 * Validates a landing document (catalogue format, ADR 0008) against the page catalogue manifest that
 * `yarn workspace @ulams/ui page-manifest` copies into this package: known components, closed props
 * that match their schema, children only where allowed, bounded depth. `$data` bindings are replaced
 * by their `$default` (the page fills the rest at render time).
 */
final class LandingValidator
{
    private const MAX_DEPTH = 12;

    /** @var array<string,mixed>|null */
    private ?array $manifest = null;

    public function __construct(private readonly JsonSchemaValidator $validator)
    {
    }

    public static function manifestPath(): string
    {
        return __DIR__ . '/../../resources/catalogue/page-manifest.json';
    }

    /** @return string[] */
    public function validate(mixed $doc): array
    {
        $this->manifest ??= self::objects((array) json_decode((string) file_get_contents(self::manifestPath()), true));
        if (!is_array($doc)) {
            return ['the landing page is missing'];
        }
        $errors = [];
        if (($doc['component'] ?? null) !== 'Page') {
            $errors[] = 'the landing document must start with a Page';
        }
        $this->node($doc, 0, 'landing', $errors);

        return array_slice($errors, 0, 10);
    }

    private function node(mixed $node, int $depth, string $path, array &$errors): void
    {
        if (!is_array($node) || !is_string($node['component'] ?? null)) {
            $errors[] = "{$path}: not a component";

            return;
        }
        $name = $node['component'];
        $spec = $this->manifest['components'][$name] ?? null;
        if ($spec === null) {
            $errors[] = "{$path}: unknown component {$name}";

            return;
        }
        if ($depth > self::MAX_DEPTH) {
            $errors[] = "{$path}: deeper than " . self::MAX_DEPTH . ' levels';

            return;
        }
        $props = $this->resolve($node['props'] ?? []);
        foreach ($this->validator->validate($props === [] ? (object) [] : $props, (array) $spec['props']) as $error) {
            $errors[] = "{$path}/{$name}: {$error}";
        }
        $children = $node['children'] ?? [];
        if ($children !== [] && !($spec['children'] ?? false)) {
            $errors[] = "{$path}/{$name}: takes no children";

            return;
        }
        foreach ((array) $children as $i => $child) {
            $this->node($child, $depth + 1, "{$path}/{$name}[{$i}]", $errors);
        }
    }

    /** An empty `properties` decodes to [] (a list); the schema validator needs an object there. */
    private static function objects(array $schema): array
    {
        foreach ($schema as $key => $value) {
            if ($key === 'properties' && $value === []) {
                $schema[$key] = new \stdClass();
            } elseif (is_array($value)) {
                $schema[$key] = self::objects($value);
            }
        }

        return $schema;
    }

    private function resolve(mixed $value): mixed
    {
        if (is_array($value) && array_key_exists('$data', $value)) {
            return $value['$default'] ?? null;
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $resolved = $this->resolve($v);
                if ($resolved !== null || !(is_array($v) && array_key_exists('$data', $v))) {
                    $out[$k] = $resolved;
                }
            }

            return $out;
        }

        return $value;
    }
}
