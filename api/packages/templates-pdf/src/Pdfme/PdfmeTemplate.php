<?php

namespace Ulams\TemplatesPdf\Pdfme;

use Ulams\TemplatesPdf\Exceptions\LegacyTemplateException;
use Ulams\TemplatesPdf\Exceptions\PdfRenderException;

/**
 * pdfme templates as stored in the `content` section of PDF templates and in
 * `fabric_pdfs.content`, and the mapping of template variables to pdfme inputs.
 *
 * Variables (e.g. "@VarUserName") are used in two ways:
 * - as the name of an editable field: the field gets the variable's value;
 * - inside the text of a read-only field ("Issued by @VarAppName" or
 *   "${VarAppName}"): the variable is replaced in place.
 * Editable fields that are not named after a variable keep their own content.
 */
class PdfmeTemplate
{
    /**
     * Decodes stored content. Accepts a JSON string, an array, or a JSON string
     * that was JSON-encoded again (the `array` cast of `fabric_pdfs.content`).
     *
     * @throws PdfRenderException
     */
    public static function decode(mixed $content): array
    {
        $decoded = $content;
        for ($i = 0; $i < 3 && is_string($decoded); $i++) {
            $decoded = json_decode($decoded, true);
        }
        if (!is_array($decoded)) {
            throw new PdfRenderException('PDF template content is not valid JSON.', 422, 'invalid_template');
        }

        return $decoded;
    }

    public static function isReportBro(array $template): bool
    {
        return array_key_exists('docElements', $template) && array_key_exists('documentProperties', $template);
    }

    public static function isPdfme(array $template): bool
    {
        return isset($template['schemas']) && is_array($template['schemas']) && array_key_exists('basePdf', $template);
    }

    /**
     * @throws PdfRenderException
     */
    public static function assertRenderable(array $template): void
    {
        if (self::isReportBro($template)) {
            throw new LegacyTemplateException();
        }
        if (!self::isPdfme($template)) {
            throw new PdfRenderException('PDF template is not a pdfme template (expected basePdf and schemas).', 422, 'invalid_template');
        }
    }

    /**
     * @return array<int, array> all fields of all pages
     */
    public static function fields(array $template): array
    {
        $fields = [];
        foreach ($template['schemas'] ?? [] as $page) {
            foreach (is_array($page) ? $page : [] as $key => $field) {
                if (is_array($field)) {
                    // pdfme < 4 stored pages as objects keyed by field name
                    $field['name'] ??= is_string($key) ? $key : null;
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Names of editable (non read-only) fields.
     *
     * @return string[]
     */
    public static function fieldNames(array $template): array
    {
        return array_values(array_filter(array_map(
            fn (array $field) => empty($field['readOnly']) ? ($field['name'] ?? null) : null,
            self::fields($template)
        )));
    }

    /**
     * Builds the request for the renderer: the template with variables
     * replaced in read-only text, and one input record.
     *
     * @param array<string, mixed> $vars variables keyed as "@VarName"
     * @return array{template: array, inputs: array<int, array<string, string>>}
     */
    public static function prepare(array $template, array $vars): array
    {
        self::assertRenderable($template);

        $replacements = self::replacements($vars);
        $lookup = self::lookup($vars);

        $input = [];
        foreach ($template['schemas'] as $pageIndex => $page) {
            foreach ($page as $fieldIndex => $field) {
                if (!is_array($field)) {
                    continue;
                }
                $name = $field['name'] ?? (is_string($fieldIndex) ? $fieldIndex : null);
                if (!empty($field['readOnly'])) {
                    if (isset($field['content']) && is_string($field['content']) && $replacements) {
                        $template['schemas'][$pageIndex][$fieldIndex]['content'] = strtr($field['content'], $replacements);
                    }
                    continue;
                }
                if ($name === null) {
                    continue;
                }
                $key = self::normalizeName($name);
                $input[$name] = array_key_exists($key, $lookup)
                    ? $lookup[$key]
                    : (is_string($field['content'] ?? null) ? strtr($field['content'], $replacements) : '');
            }
        }

        return ['template' => $template, 'inputs' => [$input]];
    }

    /**
     * Variables whose names are not used anywhere in the template.
     *
     * @param string[] $variables
     * @return string[]
     */
    public static function unusedVariables(array $template, array $variables): array
    {
        $json = json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return array_values(array_filter(
            $variables,
            fn (string $var) => !str_contains($json, $var) && !str_contains($json, '${' . ltrim($var, '@') . '}')
        ));
    }

    public static function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * "@VarUserName", "${VarUserName}" and "VarUserName" all name the same variable.
     */
    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        if (preg_match('/^\$\{(.+)\}$/', $name, $m)) {
            $name = $m[1];
        }

        return ltrim($name, '@');
    }

    /**
     * @return array<string, string> normalized variable name => value
     */
    private static function lookup(array $vars): array
    {
        $lookup = [];
        foreach ($vars as $key => $value) {
            $lookup[self::normalizeName((string) $key)] = self::stringify($value);
        }

        return $lookup;
    }

    /**
     * @return array<string, string> "@VarX" and "${VarX}" => value, for strtr
     */
    private static function replacements(array $vars): array
    {
        $replacements = [];
        foreach ($vars as $key => $value) {
            $name = self::normalizeName((string) $key);
            if ($name === '') {
                continue;
            }
            $replacements['@' . $name] = self::stringify($value);
            $replacements['${' . $name . '}'] = self::stringify($value);
        }

        return $replacements;
    }
}
