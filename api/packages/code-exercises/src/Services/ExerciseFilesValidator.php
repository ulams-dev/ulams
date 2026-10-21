<?php

namespace Ulams\CodeExercises\Services;

/**
 * Checks the files of an exercise (starter files and visible tests). Everything here is untrusted
 * input: the author may be a tenant user, and exercises also arrive through course imports and the
 * CLI. The files are only ever stored and handed to the browser runner as text; nothing in this
 * package runs them on the server. Returns messages, empty when the files are fine.
 */
class ExerciseFilesValidator
{
    /**
     * @param mixed $files a list of {path, content}
     * @return string[]
     */
    public function validate(mixed $files, string $language, bool $tests): array
    {
        $limits = (array) config('ulams_code_exercises.limits');
        $rules = (array) config('ulams_code_exercises.languages.' . $language);
        if ($rules === []) {
            return ['The language is not supported.'];
        }
        $allowed = (array) ($tests ? $rules['tests'] : $rules['extensions']);
        $max = (int) ($tests ? $limits['max_test_files'] : $limits['max_files']);

        if (!is_array($files) || !array_is_list($files)) {
            return ['It must be a list of files.'];
        }
        if ($files === []) {
            return ['It needs at least one file.'];
        }
        if (count($files) > $max) {
            return [sprintf('It has more than %d files.', $max)];
        }

        $errors = [];
        $total = 0;
        $seen = [];
        foreach ($files as $index => $file) {
            $label = '#' . ($index + 1);
            if (!is_array($file) || array_diff(array_keys($file), ['path', 'content']) !== [] || !isset($file['path'], $file['content'])) {
                $errors[] = "File {$label} must be an object with only a path and content.";
                continue;
            }
            $path = $file['path'];
            $content = $file['content'];
            if (!is_string($path) || !is_string($content)) {
                $errors[] = "File {$label} needs a text path and text content.";
                continue;
            }
            $error = $this->pathError($path, $allowed, (int) $limits['max_path_length']);
            if ($error !== null) {
                $errors[] = "File {$label} ({$this->short($path)}): {$error}";
                continue;
            }
            if (isset($seen[strtolower($path)])) {
                $errors[] = "File {$label}: the path {$path} is used twice.";
            }
            $seen[strtolower($path)] = true;
            if (str_contains($content, "\0") || !mb_check_encoding($content, 'UTF-8')) {
                $errors[] = "File {$label} ({$path}) is not UTF-8 text.";
            }
            $bytes = strlen($content);
            if ($bytes > (int) $limits['max_file_bytes']) {
                $errors[] = "File {$label} ({$path}) is larger than " . ((int) $limits['max_file_bytes'] / 1024) . ' KB.';
            }
            $total += $bytes;
        }
        if ($total > (int) $limits['max_total_bytes']) {
            $errors[] = 'The files together are larger than ' . ((int) $limits['max_total_bytes'] / 1024) . ' KB.';
        }

        return $errors;
    }

    /** @param string[] $allowed */
    private function pathError(string $path, array $allowed, int $maxLength): ?string
    {
        if ($path === '' || strlen($path) > $maxLength) {
            return 'the path must be 1 to ' . $maxLength . ' characters.';
        }
        // relative, forward slashes, no traversal, no hidden files, plain characters only
        if (!preg_match('#^[A-Za-z0-9_][A-Za-z0-9_.-]*(/[A-Za-z0-9_][A-Za-z0-9_.-]*)*$#', $path) || str_contains($path, '..')) {
            return 'use a relative path of letters, digits, "_", "-" and "." with "/" between folders.';
        }
        foreach ((array) config('ulams_code_exercises.denied_names') as $pattern) {
            if (preg_match((string) $pattern, $path)) {
                return 'this kind of file is not allowed.';
            }
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed, true)) {
            return 'the extension must be one of ' . implode(', ', $allowed) . '.';
        }

        return null;
    }

    private function short(string $value): string
    {
        return mb_substr(preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '', 0, 40);
    }
}
