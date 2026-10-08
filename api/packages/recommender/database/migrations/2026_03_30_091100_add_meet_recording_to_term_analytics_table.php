<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('term_analytics', function (Blueprint $table) {
            $table->foreignId('meet_recording_id')
                ->nullable()
                ->references('id')
                ->on('meet_recordings')
                ->nullOnDelete();
        });

        Schema::table('meet_recording_screens', function (Blueprint $table) {
            $table->foreignId('meet_recording_id')
                ->nullable()
                ->references('id')
                ->on('meet_recordings')
                ->nullOnDelete();

            $table->index(
                ['meet_recording_id', 'file_timestamp'],
                'idx_screens_recording_time'
            );
        });

        Schema::table('meet_recordings', function (Blueprint $table) {
            $table->dropUnique('meet_recordings_model_type_model_id_term_unique');

            $table->unique(['model_type', 'model_id', 'term', 'start_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('term_analytics', function (Blueprint $table) {
            $table->dropForeign('term_analytics_meet_recording_id_foreign');
            $table->dropColumn('meet_recording_id');
        });

        Schema::table('meet_recording_screens', function (Blueprint $table) {
            $table->dropIndex('idx_screens_recording_time');
            $table->dropForeign('meet_recording_screens_meet_recording_id_foreign');
            $table->dropColumn('meet_recording_id');
        });

        Schema::table('meet_recordings', function (Blueprint $table) {
            $table->dropUnique('meet_recordings_model_type_model_id_term_start_at_unique');

            $table->unique(['model_type', 'model_id', 'term']);
        });
    }
};
