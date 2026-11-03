<?php

namespace Ulams\Identity\Services\Scim;

/**
 * Applies the operations of a SCIM PatchOp request (RFC 7644 3.5.2) to the SCIM representation of a resource and
 * returns the changed representation. Nothing is written here: the caller validates the result like a PUT body and
 * writes it in one transaction, so a failing operation leaves the resource as it was.
 *
 * Handled: `add`, `replace`, `remove` (the `op` in any letter case), with and without `path`, paths with a schema
 * URN prefix (enterprise extension), sub-attributes (`name.givenName`), value paths (`members[value eq "x"]`,
 * `emails[type eq "work"].value`) and the client quirks seen in practice: Entra ID sends `"False"` strings and
 * `replace` without a path, and removes group members with `path: members` plus the list to remove as `value`;
 * Okta sends `add` for members.
 */
final class PatchApplier
{
    public const SCHEMA = 'urn:ietf:params:scim:api:messages:2.0:PatchOp';

    /** @var list<string> attributes that clients cannot change */
    private const READ_ONLY = ['id', 'meta', 'schemas', 'groups'];

    /**
     * @param list<string> $multiValued lower-case names of multi-valued attributes of the resource
     * @param list<string> $known attribute names with their canonical spelling
     * @param list<string> $schemaUrns URNs a path may start with (extension and core)
     */
    public function __construct(private readonly array $multiValued, private readonly array $known, private readonly array $schemaUrns)
    {
    }

    /**
     * @param array<string,mixed> $resource
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function apply(array $resource, array $body): array
    {
        $schemas = $body['schemas'] ?? null;
        if (!is_array($schemas) || !in_array(self::SCHEMA, array_map('strval', array_filter($schemas, 'is_scalar')), true)) {
            throw ScimError::invalidSyntax('The request must list ' . self::SCHEMA . ' in schemas.');
        }
        $operations = $body['Operations'] ?? $body['operations'] ?? null;
        if (!is_array($operations) || !array_is_list($operations) || $operations === []) {
            throw ScimError::invalidSyntax('Operations must be a non-empty list.');
        }
        if (count($operations) > 100) {
            throw ScimError::tooMany('At most 100 operations per request.');
        }
        foreach ($operations as $operation) {
            if (!is_array($operation) || !is_string($operation['op'] ?? null)) {
                throw ScimError::invalidSyntax('Every operation needs an op.');
            }
            $op = strtolower($operation['op']);
            if (!in_array($op, ['add', 'replace', 'remove'], true)) {
                throw ScimError::invalidSyntax('The op must be add, replace or remove.');
            }
            $path = $operation['path'] ?? null;
            if ($path !== null && (!is_string($path) || trim($path) === '' || strlen($path) > 500)) {
                throw ScimError::invalidPath('The path is not valid.');
            }
            $this->operation($resource, $op, $path === null ? null : trim($path), $operation['value'] ?? null, array_key_exists('value', $operation));
        }

        return $resource;
    }

    /** @param array<string,mixed> $doc */
    private function operation(array &$doc, string $op, ?string $path, mixed $value, bool $hasValue): void
    {
        if ($path === null) {
            if ($op === 'remove') {
                throw ScimError::noTarget('A remove operation needs a path.');
            }
            if (!is_array($value) || array_is_list($value)) {
                throw ScimError::invalidValue('Without a path the value must be an object of attributes.');
            }
            foreach ($value as $key => $v) {
                $key = (string) $key;
                if (in_array(strtolower($key), self::READ_ONLY, true)) {
                    continue; // clients echo these back; they are not an error without a path
                }
                if ($this->isExtensionUrn($key) && is_array($v) && !array_is_list($v)) {
                    foreach ($v as $subKey => $subValue) {
                        $this->operation($doc, $op, $this->isExtensionUrn($key) . ':' . $subKey, $subValue, true);
                    }
                    continue;
                }
                $this->operation($doc, $op, $key, $v, true);
            }

            return;
        }

        [$extension, $rest] = $this->splitUrn($path);
        if ($extension !== null && $rest === '') {
            // the whole extension object
            if ($op === 'remove') {
                unset($doc[$extension]);

                return;
            }
            if (!is_array($value) || array_is_list($value)) {
                throw ScimError::invalidValue('The extension value must be an object.');
            }
            $doc[$extension] = $op === 'replace' ? $value : array_replace($doc[$extension] ?? [], $value);

            return;
        }
        [$attr, $filter, $sub] = $this->parseAttributePath($rest);

        if (in_array(strtolower($attr), self::READ_ONLY, true) && $extension === null) {
            throw ScimError::mutability('"' . $attr . '" is read-only.');
        }
        if ($extension !== null) {
            $doc[$extension] ??= [];
            $container = &$doc[$extension];
        } else {
            $container = &$doc;
        }
        $attr = $this->canonical($attr, $container);
        $this->applyAttribute($container, $op, $attr, $filter, $sub, $value, $hasValue);
        if ($extension !== null && $doc[$extension] === []) {
            unset($doc[$extension]);
        }
    }

    /** @param array<string,mixed> $c */
    private function applyAttribute(array &$c, string $op, string $attr, ?array $filter, ?string $sub, mixed $value, bool $hasValue): void
    {
        $multi = in_array(strtolower($attr), $this->multiValued, true);
        if ($filter !== null) {
            if (!$multi) {
                throw ScimError::invalidPath('"' . $attr . '" is not multi-valued, so it cannot take a filter.');
            }
            $list = array_values((array) ($c[$attr] ?? []));
            $hits = [];
            foreach ($list as $i => $element) {
                if (is_array($element) && $this->matches($filter, $element)) {
                    $hits[] = $i;
                }
            }
            if ($op === 'remove') {
                foreach ($hits as $i) {
                    if ($sub !== null) {
                        unset($list[$i][$this->canonical($sub, $list[$i])]);
                    } else {
                        unset($list[$i]);
                    }
                }
                $c[$attr] = array_values($list);

                return;
            }
            if ($hits === []) {
                throw ScimError::noTarget('No value of "' . $attr . '" matches the filter.');
            }
            foreach ($hits as $i) {
                if ($sub !== null) {
                    $list[$i][$this->canonical($sub, $list[$i])] = $value;
                } else {
                    if (!is_array($value) || array_is_list($value)) {
                        throw ScimError::invalidValue('The value must be an object.');
                    }
                    $list[$i] = $op === 'replace' ? $value : array_replace($list[$i], $value);
                }
            }
            $c[$attr] = array_values($list);

            return;
        }

        if ($sub !== null) {
            if ($multi) {
                $list = array_values((array) ($c[$attr] ?? []));
                if ($op === 'remove') {
                    foreach ($list as $i => $_) {
                        unset($list[$i][$this->canonical($sub, $list[$i])]);
                    }
                } elseif ($list === []) {
                    $list = [[$sub => $value]];
                } else {
                    foreach ($list as $i => $_) {
                        $list[$i][$this->canonical($sub, $list[$i])] = $value;
                    }
                }
                $c[$attr] = array_values($list);

                return;
            }
            if ($op === 'remove') {
                if (isset($c[$attr]) && is_array($c[$attr])) {
                    unset($c[$attr][$this->canonical($sub, $c[$attr])]);
                }

                return;
            }
            if (isset($c[$attr]) && !is_array($c[$attr])) {
                throw ScimError::invalidPath('"' . $attr . '" has no sub-attributes.');
            }
            $c[$attr] ??= [];
            $c[$attr][$this->canonical($sub, $c[$attr])] = $value;

            return;
        }

        if ($op === 'remove') {
            if ($multi && $hasValue && is_array($value)) {
                // Entra ID: remove the listed members/values from the attribute
                $drop = array_map(fn ($v) => strtolower((string) (is_array($v) ? ($v['value'] ?? '') : $v)), array_values(array_filter($value, fn ($v) => is_array($v) || is_scalar($v))));
                $c[$attr] = array_values(array_filter((array) ($c[$attr] ?? []), fn ($e) => !in_array(strtolower((string) (is_array($e) ? ($e['value'] ?? '') : $e)), $drop, true)));

                return;
            }
            unset($c[$attr]);

            return;
        }

        if ($multi) {
            $incoming = $this->asList($value, $attr);
            if ($op === 'replace') {
                $c[$attr] = $incoming;
            } else {
                $existing = array_values((array) ($c[$attr] ?? []));
                foreach ($incoming as $element) {
                    $key = strtolower((string) ($element['value'] ?? ''));
                    $existing = array_values(array_filter($existing, fn ($e) => $key === '' || strtolower((string) ($e['value'] ?? '')) !== $key));
                    if (($element['primary'] ?? false) === true) {
                        foreach ($existing as $i => $_) {
                            $existing[$i]['primary'] = false;
                        }
                    }
                    $existing[] = $element;
                }
                $c[$attr] = $existing;
            }

            return;
        }

        if ($op === 'add' && is_array($value) && !array_is_list($value) && isset($c[$attr]) && is_array($c[$attr])) {
            $c[$attr] = array_replace($c[$attr], $value);
        } else {
            $c[$attr] = $value;
        }
    }

    /** @return list<array<string,mixed>> */
    private function asList(mixed $value, string $attr): array
    {
        if (is_array($value) && array_is_list($value)) {
            $out = [];
            foreach ($value as $element) {
                if (!is_array($element) || array_is_list($element)) {
                    throw ScimError::invalidValue('Every value of "' . $attr . '" must be an object.');
                }
                $out[] = $element;
            }

            return $out;
        }
        if (is_array($value)) {
            return [$value];
        }

        throw ScimError::invalidValue('"' . $attr . '" takes an object or a list of objects.');
    }

    /** @return array{0:?string,1:string} schema URN (as listed) and the rest of the path */
    private function splitUrn(string $path): array
    {
        foreach ($this->schemaUrns as $urn) {
            if (strcasecmp($path, $urn) === 0) {
                return [$this->isCore($urn) ? null : $urn, ''];
            }
            if (stripos($path, $urn . ':') === 0) {
                return [$this->isCore($urn) ? null : $urn, substr($path, strlen($urn) + 1)];
            }
        }

        return [null, $path];
    }

    private function isCore(string $urn): bool
    {
        return str_contains($urn, ':schemas:core:');
    }

    private function isExtensionUrn(string $key): string|false
    {
        foreach ($this->schemaUrns as $urn) {
            if (!$this->isCore($urn) && strcasecmp($urn, $key) === 0) {
                return $urn;
            }
        }

        return false;
    }

    /**
     * @return array{0:string,1:?array<string,mixed>,2:?string} attribute, value-path filter, sub-attribute
     */
    private function parseAttributePath(string $rest): array
    {
        $open = strpos($rest, '[');
        if ($open === false) {
            $parts = explode('.', $rest);
            if (count($parts) > 2 || !$this->name($parts[0]) || (isset($parts[1]) && !$this->name($parts[1]))) {
                throw ScimError::invalidPath('The path "' . mb_substr($rest, 0, 80) . '" is not valid.');
            }

            return [$parts[0], null, $parts[1] ?? null];
        }
        $attr = substr($rest, 0, $open);
        if (!$this->name($attr)) {
            throw ScimError::invalidPath('The path is not valid.');
        }
        $inString = false;
        $close = null;
        for ($i = $open + 1, $n = strlen($rest); $i < $n; $i++) {
            $ch = $rest[$i];
            if ($inString) {
                if ($ch === '\\') {
                    $i++;
                } elseif ($ch === '"') {
                    $inString = false;
                }
            } elseif ($ch === '"') {
                $inString = true;
            } elseif ($ch === ']') {
                $close = $i;
                break;
            }
        }
        if ($close === null) {
            throw ScimError::invalidPath('The value path is not closed.');
        }
        $filter = (new FilterParser())->parse(substr($rest, $open + 1, $close - $open - 1));
        $tail = substr($rest, $close + 1);
        $sub = null;
        if ($tail !== '') {
            if ($tail[0] !== '.' || !$this->name(substr($tail, 1))) {
                throw ScimError::invalidPath('The path continues after the value path in a way that is not valid.');
            }
            $sub = substr($tail, 1);
        }

        return [$attr, $filter, $sub];
    }

    private function name(string $s): bool
    {
        return (bool) preg_match('/^[A-Za-z$][A-Za-z0-9$_\-]*$/', $s);
    }

    /** @param array<string,mixed> $container */
    private function canonical(string $name, array $container): string
    {
        foreach (array_keys($container) as $key) {
            if (is_string($key) && strcasecmp($key, $name) === 0) {
                return $key;
            }
        }
        foreach ($this->known as $known) {
            if (strcasecmp($known, $name) === 0) {
                return $known;
            }
        }

        return $name;
    }

    /**
     * Evaluates a value-path filter against one element of a multi-valued attribute.
     *
     * @param array<string,mixed> $node
     * @param array<string,mixed> $element
     */
    private function matches(array $node, array $element): bool
    {
        switch ($node['t']) {
            case 'and':
                return $this->matches($node['l'], $element) && $this->matches($node['r'], $element);
            case 'or':
                return $this->matches($node['l'], $element) || $this->matches($node['r'], $element);
            case 'not':
                return !$this->matches($node['n'], $element);
            case 'pr':
                $v = $this->element($element, $node['attr']);

                return $v !== null && $v !== '';
            case 'cmp':
                return $this->compare($this->element($element, $node['attr']), $node['op'], $node['value']);
            default:
                throw ScimError::invalidPath('Nested value paths are not supported.');
        }
    }

    /** @param array<string,mixed> $element */
    private function element(array $element, string $attr): mixed
    {
        foreach ($element as $key => $value) {
            if (is_string($key) && strcasecmp($key, $attr) === 0) {
                return $value;
            }
        }

        return null;
    }

    private function compare(mixed $actual, string $op, mixed $expected): bool
    {
        if (is_bool($actual) || is_bool($expected)) {
            $a = is_string($actual) ? strtolower($actual) === 'true' : $actual;

            return $op === 'ne' ? $a !== $expected : $a === $expected;
        }
        if ($actual === null) {
            return $op === 'ne' && $expected !== null;
        }
        if (is_string($actual) && is_string($expected)) {
            $a = mb_strtolower($actual);
            $e = mb_strtolower($expected);
        } else {
            $a = $actual;
            $e = $expected;
        }

        return match ($op) {
            'eq' => $a == $e, 'ne' => $a != $e,
            'co' => is_string($a) && is_string($e) && str_contains($a, $e),
            'sw' => is_string($a) && is_string($e) && str_starts_with($a, $e),
            'ew' => is_string($a) && is_string($e) && str_ends_with($a, $e),
            'gt' => $a > $e, 'ge' => $a >= $e, 'lt' => $a < $e, 'le' => $a <= $e,
            default => false,
        };
    }
}
