<?php

namespace Ulams\Ai\Services;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Validates decoded JSON against a JSON Schema (draft 2020-12) with opis/json-schema and returns
 * readable error lines ("/modules/0/title: The data (null) must match the type: string").
 *
 * The provider accepts a subset of JSON Schema for structured outputs, so `forProvider()` strips
 * the keywords it does not support (length, range, pattern, item counts above 1). The full schema
 * is still enforced here after the response arrives.
 */
final class JsonSchemaValidator
{
    private const PROVIDER_DROPPED = [
        '$schema', '$id', 'title', 'examples', 'minLength', 'maxLength', 'pattern', 'minimum', 'maximum',
        'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf', 'maxItems', 'uniqueItems', 'minProperties',
        'maxProperties', '$comment',
    ];

    private const PROVIDER_FORMATS = ['date-time', 'time', 'date', 'duration', 'email', 'hostname', 'uri', 'ipv4', 'ipv6', 'uuid'];

    /**
     * @param array<string,mixed> $schema
     * @return string[] error messages; empty when valid
     */
    public function validate(mixed $data, array $schema, int $maxErrors = 20): array
    {
        $validator = new Validator();
        $validator->setMaxErrors($maxErrors);
        $validator->setStopAtFirstError(false);
        $validator->parser()->setOption('defaultDraft', '2020-12');

        $result = $validator->validate(self::toObject($data), self::toObject($schema));
        if ($result->isValid()) {
            return [];
        }

        $lines = [];
        $formatted = (new ErrorFormatter())->format($result->error(), true);
        foreach ($formatted as $path => $messages) {
            foreach ((array) $messages as $message) {
                $lines[] = ($path === '' ? '/' : $path) . ': ' . $message;
            }
        }

        return array_slice(array_values(array_unique($lines)), 0, $maxErrors);
    }

    /**
     * The schema sent to the provider.
     *
     * @param array<string,mixed> $schema
     * @return array<string,mixed>
     */
    public static function forProvider(array $schema): array
    {
        $out = [];
        foreach ($schema as $key => $value) {
            if (in_array($key, self::PROVIDER_DROPPED, true)) {
                continue;
            }
            if ($key === 'format' && is_string($value) && !in_array($value, self::PROVIDER_FORMATS, true)) {
                continue;
            }
            if ($key === 'minItems' && is_int($value) && $value > 1) {
                $value = 1;
            }
            if (in_array($key, ['properties', '$defs', 'definitions'], true) && is_array($value)) {
                $value = array_map(fn ($s) => is_array($s) ? self::forProvider($s) : $s, $value);
            } elseif (in_array($key, ['items', 'additionalProperties', 'not'], true) && is_array($value)) {
                $value = self::forProvider($value);
            } elseif (in_array($key, ['anyOf', 'allOf', 'oneOf', 'prefixItems'], true) && is_array($value)) {
                $value = array_map(fn ($s) => is_array($s) ? self::forProvider($s) : $s, $value);
            }
            $out[$key] = $value;
        }
        if (isset($out['oneOf']) && !isset($out['anyOf'])) {
            $out['anyOf'] = $out['oneOf'];
            unset($out['oneOf']);
        }

        return $out;
    }

    /** Arrays → stdClass for objects, lists stay arrays (opis works on decoded JSON). */
    public static function toObject(mixed $value): mixed
    {
        if (is_array($value)) {
            if ($value !== [] && array_is_list($value)) {
                return array_map([self::class, 'toObject'], $value);
            }
            if ($value === []) {
                return [];
            }
            $object = new \stdClass();
            foreach ($value as $k => $v) {
                $object->{$k} = self::toObject($v);
            }

            return $object;
        }

        return $value;
    }
}
