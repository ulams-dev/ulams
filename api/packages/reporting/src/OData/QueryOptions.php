<?php

namespace Ulams\Reporting\OData;

use Illuminate\Http\Request;

/**
 * The OData system query options a feed understands: `$select`, `$top`, `$skip`, `$count` and `$format=json`. The saved
 * report already is the filter, the grouping and the order, so `$filter`, `$orderby`, `$apply`, `$expand` and the others
 * answer 501 with a message that says so, instead of being ignored (a client that believed its filter ran would show wrong
 * numbers). Options that are not OData's (no leading dollar) are ignored, as the standard allows.
 */
final class QueryOptions
{
    private const UNSUPPORTED = ['$filter', '$orderby', '$apply', '$expand', '$search', '$compute', '$levels', '$skiptoken', '$deltatoken', '$schemaversion'];

    /** @param list<string>|null $select */
    private function __construct(public readonly ?array $select, public readonly ?int $top, public readonly int $skip, public readonly bool $count)
    {
    }

    /** @param list<string> $properties the properties of the entity, to check `$select` against */
    public static function parse(Request $request, array $properties): self
    {
        $query = $request->query();
        foreach ($query as $name => $value) {
            $name = (string) $name;
            if (in_array($name, self::UNSUPPORTED, true)) {
                throw ODataException::notImplemented($name);
            }
            if (str_starts_with($name, '$') && !in_array($name, ['$select', '$top', '$skip', '$count', '$format'], true)) {
                throw new ODataException(400, "Unknown system query option {$name}.");
            }
        }

        if (isset($query['$format']) && !in_array(strtolower((string) $query['$format']), ['json', 'application/json'], true)) {
            throw ODataException::notImplemented('$format=' . $query['$format']);
        }

        $select = null;
        if (isset($query['$select']) && trim((string) $query['$select']) !== '' && trim((string) $query['$select']) !== '*') {
            $select = array_values(array_unique(array_map('trim', explode(',', (string) $query['$select']))));
            $unknown = array_diff($select, $properties);
            if ($unknown !== []) {
                throw new ODataException(400, 'Unknown property in $select: ' . implode(', ', $unknown) . '.');
            }
        }

        return new self($select, self::integer($query, '$top'), self::integer($query, '$skip') ?? 0, self::boolean($query));
    }

    /** @param array<string,mixed> $query */
    private static function integer(array $query, string $name): ?int
    {
        if (!isset($query[$name])) {
            return null;
        }
        $value = (string) $query[$name];
        if (!preg_match('/^\d{1,9}$/', $value)) {
            throw new ODataException(400, "{$name} must be a non-negative integer.");
        }

        return (int) $value;
    }

    /** @param array<string,mixed> $query */
    private static function boolean(array $query): bool
    {
        if (!isset($query['$count'])) {
            return false;
        }
        $value = strtolower((string) $query['$count']);
        if (!in_array($value, ['true', 'false'], true)) {
            throw new ODataException(400, '$count must be true or false.');
        }

        return $value === 'true';
    }
}
