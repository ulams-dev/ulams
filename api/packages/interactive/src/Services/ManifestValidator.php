<?php

namespace Ulams\Interactive\Services;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates `ulams-interactive.json` (ADR 0086): the JSON Schema first, then the rules a schema
 * cannot say (step texts for every locale, unique step ids, licence allow-list, files that must
 * exist in the archive). Returns the decoded manifest or throws a 422 naming the problems.
 */
class ManifestValidator
{
    public const SCHEMA = __DIR__ . '/../../resources/schemas/ulams-interactive/v1.json';

    /**
     * @param string[] $paths every file path in the archive (normalised, relative to its root)
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function validate(string $json, array $paths): array
    {
        $errors = [];
        $data = json_decode($json);
        if (!is_object($data)) {
            throw ValidationException::withMessages(['manifest' => ['The manifest is not valid JSON.']]);
        }

        $validator = new Validator();
        $validator->setMaxErrors(15);
        $validator->parser()->setOption('defaultDraft', '2020-12');
        $result = $validator->validate($data, json_decode((string) file_get_contents(self::SCHEMA)));
        if (!$result->isValid()) {
            foreach ((new ErrorFormatter())->format($result->error(), true) as $path => $messages) {
                foreach ((array) $messages as $message) {
                    $errors[] = ($path === '' ? '/' : $path) . ': ' . $message;
                }
            }
            throw ValidationException::withMessages(['manifest' => array_slice(array_values(array_unique($errors)), 0, 15)]);
        }

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode($json, true);

        if (!in_array($manifest['licence'], (array) config('ulams_interactive.licences', []), true)) {
            $errors[] = sprintf('licence: "%s" is not an accepted SPDX id (%s).', $manifest['licence'], implode(', ', (array) config('ulams_interactive.licences')));
        }
        $locales = $manifest['locales'];
        if (!in_array($manifest['defaultLocale'], $locales, true)) {
            $errors[] = 'defaultLocale: must be one of locales.';
        }
        if (count($manifest['steps']) > (int) config('ulams_interactive.max_steps', 200)) {
            $errors[] = 'steps: too many steps.';
        }

        $ids = [];
        foreach ($manifest['steps'] as $i => $step) {
            if (isset($ids[$step['id']])) {
                $errors[] = "steps[{$i}].id: \"{$step['id']}\" is used twice.";
            }
            $ids[$step['id']] = true;
            foreach ($locales as $locale) {
                foreach (['text', 'title'] as $field) {
                    if (!isset($step[$field][$locale]) || trim((string) $step[$field][$locale]) === '') {
                        $errors[] = "steps[{$i}].{$field}: missing for the locale \"{$locale}\" (every step needs a text alternative in every locale).";
                    }
                }
            }
        }
        foreach ((array) ($manifest['title'] ?? []) as $locale => $_) {
            if (!in_array($locale, $locales, true)) {
                $errors[] = "title: the locale \"{$locale}\" is not listed in locales.";
            }
        }
        if (!isset($manifest['title'][$manifest['defaultLocale']])) {
            $errors[] = 'title: needs the defaultLocale.';
        }

        $files = array_flip($paths);
        $entry = (string) ($manifest['entry'] ?? 'index.html');
        if (!isset($files[$entry])) {
            $errors[] = "entry: \"{$entry}\" is not in the archive.";
        } elseif (!preg_match('/\.html?$/i', $entry)) {
            $errors[] = 'entry: must be an .html file.';
        }
        foreach ($manifest['steps'] as $i => $step) {
            if (isset($step['poster']) && !isset($files[$step['poster']])) {
                $errors[] = "steps[{$i}].poster: \"{$step['poster']}\" is not in the archive.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['manifest' => $errors]);
        }
        $manifest['entry'] = $entry;

        return $manifest;
    }
}
