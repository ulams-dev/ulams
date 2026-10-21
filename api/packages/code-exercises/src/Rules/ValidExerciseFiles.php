<?php

namespace Ulams\CodeExercises\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Ulams\CodeExercises\Services\ExerciseFilesValidator;

/**
 * The files of an exercise: a list of {path, content}, or the same list as a JSON string (the admin
 * posts topics as multipart form data). `$tests` selects the test-file rules.
 */
class ValidExerciseFiles implements ValidationRule, DataAwareRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly bool $tests = false)
    {
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                $fail("The {$attribute} must be a JSON list of files.");

                return;
            }
            $value = $decoded;
        }
        $language = is_string($this->data['language'] ?? null) ? $this->data['language'] : 'javascript';
        $errors = app(ExerciseFilesValidator::class)->validate($value, $language, $this->tests);
        foreach ($errors as $error) {
            $fail("The {$attribute} are not valid: {$error}");
        }
        // a test file cannot share a path with a starter file: the runner would not know which one an import means
        if ($this->tests && $errors === []) {
            $starter = $this->data['files'] ?? [];
            $starter = is_string($starter) ? (json_decode($starter, true) ?: []) : $starter;
            $taken = array_map(fn ($f) => strtolower((string) ($f['path'] ?? '')), is_array($starter) ? $starter : []);
            foreach ((array) $value as $file) {
                if (in_array(strtolower((string) ($file['path'] ?? '')), $taken, true)) {
                    $fail("The {$attribute} are not valid: the path {$file['path']} is also a starter file.");
                }
            }
        }
    }
}
