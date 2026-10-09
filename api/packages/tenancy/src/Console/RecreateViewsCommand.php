<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recreates the SQL views with the class names of the current code. A view created before the
 * package rename keeps the old names in its `event_type` column. Run for a tenant with --domain.
 */
class RecreateViewsCommand extends Command
{
    protected $signature = 'ulams:db:recreate-views';

    protected $description = 'Recreate the searchable_events SQL view with the current model class names';

    public function handle(): int
    {
        if (
            !Schema::hasColumns('webinars', ['yt_id', 'yt_stream_key', 'yt_stream_url', 'active_from', 'active_to', 'status'])
            || !Schema::hasColumns('stationary_events', ['started_at', 'finished_at'])
        ) {
            $this->line('webinars or stationary_events missing; nothing to recreate.');

            return self::SUCCESS;
        }

        $webinar = \App\Models\Webinar::class;
        $stationary = \App\Models\StationaryEvent::class;
        if (DB::connection() instanceof MySqlConnection) {
            $webinar = str_replace('\\', '\\\\', $webinar);
            $stationary = str_replace('\\', '\\\\', $stationary);
        }

        // same definition as database/migrations/2022_05_06_122322_update_searchable_event_view.php
        $query = "SELECT webinars.id as event_id,
                '{$webinar}' as event_type,
                webinars.active_from as start_date,
                webinars.active_to as end_date,
                created_at
                FROM webinars
                WHERE status='published' AND (yt_id IS NOT NULL AND yt_stream_key IS NOT NULL AND yt_stream_url IS NOT NULL)
                UNION
                SELECT
                stationary_events.id as event_id,
                '{$stationary}' as event_class,
                stationary_events.started_at as start_date,
                stationary_events.finished_at as end_date,
                created_at
                FROM stationary_events
                ORDER BY created_at desc";

        DB::statement('DROP VIEW IF EXISTS searchable_events');
        DB::statement('CREATE VIEW searchable_events AS ' . $query);
        $this->line('searchable_events recreated.');

        return self::SUCCESS;
    }
}
