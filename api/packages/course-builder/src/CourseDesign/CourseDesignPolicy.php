<?php

namespace Ulams\CourseBuilder\CourseDesign;

use RuntimeException;
use Ulams\Ai\Services\JsonSchemaValidator;
use Ulams\CourseBuilder\ContentTypes\ContentTypeRegistry;
use Ulams\CourseBuilder\ContentTypes\H5pLibraries;

/**
 * Shared course-design knowledge for the API and local agents (ADR 0103).
 *
 * The checked-in policy is the complete inventory. Runtime views add availability from this
 * installation; an inventory entry alone never grants the model permission to generate it.
 */
final class CourseDesignPolicy
{
    /** @var array<string,mixed>|null */
    private ?array $document = null;

    public function __construct(
        private readonly JsonSchemaValidator $validator,
        private readonly ContentTypeRegistry $contentTypes,
        private readonly H5pLibraries $h5p,
    ) {
    }

    public static function root(): string
    {
        return __DIR__ . '/../../resources/course-design';
    }

    /** @return array<string,mixed> */
    public function document(): array
    {
        if ($this->document !== null) {
            return $this->document;
        }

        $document = self::read('policy.json');
        $errors = $this->validator->validate($document, self::read('policy.schema.json'));
        if ($errors !== []) {
            throw new RuntimeException('Invalid course-design policy: ' . implode('; ', array_slice($errors, 0, 10)));
        }

        return $this->document = $document;
    }

    /** @return array<int,array<string,mixed>> */
    public function capabilities(bool $withAvailability = true): array
    {
        $capabilities = array_values((array) ($this->document()['capabilities'] ?? []));
        if (!$withAvailability) {
            return $capabilities;
        }

        return array_map(fn (array $capability) => $capability + [
            'available' => $this->isAvailable($capability),
        ], $capabilities);
    }

    /** The subset the current API is allowed to generate itself. @return array<int,array<string,mixed>> */
    public function apiGeneratable(): array
    {
        return array_values(array_filter(
            $this->capabilities(),
            fn (array $capability) => $capability['authoring'] === 'api-generated' && $capability['available'],
        ));
    }

    /** Compact, stable context for prompts; detailed records remain in the policy. */
    public function modelSummary(): string
    {
        $shells = array_map(
            fn (array $shell) => "{$shell['id']}: {$shell['useWhen']}",
            (array) $this->document()['shells'],
        );
        $capabilities = array_map(
            fn (array $capability) => "{$capability['id']} [{$capability['family']}]: {$capability['purpose']}",
            $this->apiGeneratable(),
        );

        return "Learner experience shells:\n- " . implode("\n- ", $shells)
            . "\n\nAPI-generatable building blocks on this installation:\n- "
            . implode("\n- ", $capabilities);
    }

    /** @param array<string,mixed> $capability */
    private function isAvailable(array $capability): bool
    {
        if (($capability['authoring'] ?? null) === 'unavailable') {
            return false;
        }

        $runtime = (array) ($capability['runtime'] ?? []);

        return match ($runtime['kind'] ?? null) {
            'content-type' => isset($this->contentTypes->enabled()[(string) ($runtime['key'] ?? '')]),
            'h5p-library' => isset($this->h5p->present()[(string) ($runtime['key'] ?? '')]),
            default => true,
        };
    }

    /** @return array<string,mixed> */
    private static function read(string $file): array
    {
        $path = self::root() . '/' . $file;
        if (!is_file($path)) {
            throw new RuntimeException("Course-design policy file is missing: {$path}");
        }

        return (array) json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
