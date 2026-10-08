<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Migration files that carried the legacy project prefix were renamed when the
 * project became ulams (e.g. 2019_02_18_000001_<legacy>_create_users_table).
 * Databases created before the rename still record the old file names in the
 * `migrations` table, so Laravel would treat the renamed files as pending and
 * try to create existing tables again. This rewrites the recorded names before
 * any migrate* command runs.
 *
 * The legacy prefix is assembled from fragments so that a project-wide search
 * and replace cannot silently turn this into a no-op.
 */
class LegacyMigrationNames
{
    public static function rename(): void
    {
        // Longest prefix first: `<legacy>lms_` must not be rewritten as `<legacy>_`.
        $prefixes = ['escola' . 'lms_', 'escola' . '_'];

        try {
            if (!Schema::hasTable('migrations')) {
                return;
            }
            foreach ($prefixes as $legacy) {
                DB::table('migrations')
                    ->where('migration', 'like', '%\\_' . $legacy . '%')
                    ->orderBy('id')
                    ->get(['id', 'migration'])
                    ->each(fn ($row) => DB::table('migrations')
                        ->where('id', $row->id)
                        ->update(['migration' => str_replace('_' . $legacy, '_ulams_', $row->migration)]));
            }
        } catch (Throwable $e) {
            // No database yet (fresh install or misconfigured env): migrate will report the real error.
        }
    }
}
