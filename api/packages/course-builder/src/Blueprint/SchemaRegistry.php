<?php

namespace Ulams\CourseBuilder\Blueprint;

use Ulams\Ai\Services\JsonSchemaValidator;

/** Loads and validates against the package's JSON Schemas (`resources/schemas`). */
final class SchemaRegistry
{
    /** @var array<string,array<string,mixed>> */
    private array $cache = [];

    public function __construct(private readonly JsonSchemaValidator $validator)
    {
    }

    public static function root(): string
    {
        return __DIR__ . '/../../resources/schemas';
    }

    /** @return array<string,mixed> e.g. `course-brief/v1`, `outputs/outline` */
    public function get(string $name): array
    {
        return $this->cache[$name] ??= (array) json_decode((string) file_get_contents(self::root() . "/{$name}.json"), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Validates a blueprint against the schema of its own `schemaVersion` (v1 documents stay valid). @return string[] */
    public function validateBlueprint(array $doc): array
    {
        return $this->validate('course-blueprint/v' . ((int) ($doc['schemaVersion'] ?? 1) >= 2 ? 2 : 1), $doc);
    }

    /** @return string[] */
    public function validate(string $name, mixed $data): array
    {
        return $this->validator->validate($data, $this->get($name));
    }
}
