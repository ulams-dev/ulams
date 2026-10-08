<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The project was renamed to ulams: PHP namespaces moved from the legacy
 * `EscolaLms\` prefix to `Ulams\`, and config keys from `escolalms_` to `ulams_`.
 * Databases created before the rename still store the old names in polymorphic
 * columns (topicable_type, model_type, productable_type, ...), notification types,
 * JSON payloads and administrable config keys. This migration rewrites them.
 *
 * The legacy strings are assembled from fragments on purpose, so that a future
 * project-wide search and replace cannot silently turn this migration into a no-op.
 */
class RenameLegacyIdentifiersToUlams extends Migration
{
    /** Tables whose values are PHP-serialized or transient; rewriting them would corrupt string lengths. */
    private const SKIP_TABLES = ['migrations', 'jobs', 'failed_jobs', 'job_batches', 'sessions', 'cache', 'cache_locks'];

    public function up(): void
    {
        $this->rewrite('Escola' . 'Lms\\', 'Ulams\\', 'escola' . 'lms_', 'ulams_');
    }

    public function down(): void
    {
        $this->rewrite('Ulams\\', 'Escola' . 'Lms\\', 'ulams_', 'escola' . 'lms_');
    }

    private function rewrite(string $fromNamespace, string $toNamespace, string $fromKey, string $toKey): void
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['pgsql', 'mysql'], true)) {
            return; // sqlite test databases are created fresh and never hold legacy names
        }

        foreach ($this->stringColumns($driver) as [$table, $column, $type]) {
            if (in_array($table, self::SKIP_TABLES, true)) {
                continue;
            }
            $t = $this->quote($driver, $table);
            $c = $this->quote($driver, $column);

            if (in_array($type, ['json', 'jsonb'], true)) {
                // In JSON text a backslash is escaped, so match the escaped form.
                $from = str_replace('\\', '\\\\', $fromNamespace);
                $to = str_replace('\\', '\\\\', $toNamespace);
                if ($driver === 'pgsql') {
                    DB::update("UPDATE {$t} SET {$c} = REPLACE({$c}::text, ?, ?)::{$type} WHERE strpos({$c}::text, ?) > 0", [$from, $to, $from]);
                } else {
                    DB::update("UPDATE {$t} SET {$c} = REPLACE({$c}, ?, ?) WHERE LOCATE(?, {$c}) > 0", [$from, $to, $from]);
                }
                continue;
            }

            $contains = $driver === 'pgsql' ? "strpos({$c}, ?) > 0" : "LOCATE(?, {$c}) > 0";
            DB::update("UPDATE {$t} SET {$c} = REPLACE({$c}, ?, ?) WHERE {$contains}", [$fromNamespace, $toNamespace, $fromNamespace]);

            // Config keys only ever start with the prefix (e.g. escolalms_auth.registration).
            // The lengths are computed integers, so they are inlined; Postgres cannot infer types of bound CONCAT/LEFT arguments.
            $len = strlen($fromKey);
            $from = $len + 1;
            $text = $driver === 'pgsql' ? 'text' : 'char';
            DB::update(
                "UPDATE {$t} SET {$c} = CONCAT(CAST(? AS {$text}), SUBSTRING({$c}, {$from})) WHERE LEFT({$c}, {$len}) = CAST(? AS {$text})",
                [$toKey, $fromKey]
            );
        }
    }

    /** @return array<int, array{0: string, 1: string, 2: string}> */
    private function stringColumns(string $driver): array
    {
        $schema = $driver === 'pgsql' ? 'current_schema()' : 'DATABASE()';
        $rows = DB::select(
            "SELECT c.table_name AS tbl, c.column_name AS col, c.data_type AS type
               FROM information_schema.columns c
               JOIN information_schema.tables t
                 ON t.table_schema = c.table_schema AND t.table_name = c.table_name
              WHERE c.table_schema = {$schema}
                AND t.table_type = 'BASE TABLE'
                AND c.data_type IN ('character varying', 'varchar', 'text', 'mediumtext', 'longtext', 'character', 'char', 'json', 'jsonb')"
        );

        return array_map(fn ($r) => [$r->tbl, $r->col, $r->type], $rows);
    }

    private function quote(string $driver, string $identifier): string
    {
        return $driver === 'pgsql' ? '"' . str_replace('"', '""', $identifier) . '"' : '`' . str_replace('`', '``', $identifier) . '`';
    }
}
