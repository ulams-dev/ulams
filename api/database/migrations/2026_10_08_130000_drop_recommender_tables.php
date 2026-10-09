<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The recommender package was removed from the API. It owned an analytics cache
 * (meeting frame aggregates, term analytics, meeting recordings and their screens)
 * fed by an external recommender microservice; nothing else reads these tables.
 * This migration drops them on existing installs, forgets the package's migration
 * records and removes its keys from the administrable config.
 *
 * Irreversible: down() does not recreate the tables or restore the data.
 */
class DropRecommenderTables extends Migration
{
    /** Children first: aggregated_frames references term_analytics; both others reference meet_recordings. */
    private const TABLES = ['aggregated_frames', 'term_analytics', 'meet_recording_screens', 'meet_recordings'];

    private const MIGRATIONS = [
        '2026_02_04_091100_create_aggregated_frames_table',
        '2026_03_04_091100_add_max_emotion_to_aggregated_frames_table',
        '2026_03_17_091100_add_meet_users_count_to_aggregated_frames_table',
        '2026_03_23_091100_created_term_analytics_table',
        '2026_03_26_091100_create_meet_recordings_table',
        '2026_03_30_091100_add_meet_recording_to_term_analytics_table',
        '2026_04_01_091100_change_term_analytics_unique',
        '2026_04_07_091100_add_url_expires_at_in_meet_recordings_table',
        '2026_04_14_091100_add_mean_predicted_rating_to_term_analytic_table',
        '2026_04_27_091100_add_processing_video_to_meet_recording_table',
        '2026_04_28_091100_add_satisfaction_models_to_term_analytics_table',
    ];

    private const CONFIG_KEY_PREFIX = 'ulams_recommender.';

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }

        DB::table('migrations')->whereIn('migration', self::MIGRATIONS)->delete();

        $this->removeAdministrableConfigKeys();
    }

    public function down(): void
    {
        // Irreversible: the dropped analytics data cannot be restored.
    }

    private function removeAdministrableConfigKeys(): void
    {
        if (!Schema::hasTable('config')) {
            return;
        }

        $row = DB::table('config')->where('id', 1)->first();
        if ($row === null) {
            return;
        }

        $value = json_decode($row->value, true);
        if (!is_array($value)) {
            return;
        }

        $filtered = array_filter(
            $value,
            fn ($key) => !str_starts_with((string) $key, self::CONFIG_KEY_PREFIX),
            ARRAY_FILTER_USE_KEY
        );

        if (count($filtered) !== count($value)) {
            DB::table('config')->where('id', 1)->update(['value' => json_encode($filtered)]);
        }
    }
}
