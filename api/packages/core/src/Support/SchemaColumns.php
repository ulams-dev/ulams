<?php

namespace Ulams\Core\Support;

use Illuminate\Support\Facades\DB;

/**
 * Column listings, memoised per database and table for the life of the PHP process. Runtime
 * `Schema::hasColumn()` / `getColumnListing()` is a catalogue query each time (a `pg_attribute`
 * lookup on PostgreSQL); list and product endpoints made dozens per request.
 *
 * Keyed by database name, so a php-fpm worker that serves several tenants keeps one entry per
 * tenant database. Call flush() after a migration in the same process.
 */
class SchemaColumns
{
    /** @var array<string, list<string>> */
    private static array $columns = [];

    /** @return list<string> */
    public static function listing(string $table, ?string $connection = null): array
    {
        $db = DB::connection($connection);
        $key = $db->getName() . '|' . $db->getDatabaseName() . '|' . $db->getTablePrefix() . $table;

        return self::$columns[$key] ??= array_values($db->getSchemaBuilder()->getColumnListing($db->getTablePrefix() . $table));
    }

    public static function has(string $table, string $column, ?string $connection = null): bool
    {
        return in_array(strtolower($column), array_map('strtolower', self::listing($table, $connection)), true);
    }

    public static function flush(): void
    {
        self::$columns = [];
    }
}
