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
        Schema::table('aggregated_frames', function (Blueprint $table) {
            $table->unsignedInteger('meet_users_count')->nullable();

            $table->index(
                ['model_type', 'model_id', 'term'],
                'idx_frames_lookup'
            );

            $table->index(
                ['model_type', 'model_id', 'term', 'window_start'],
                'idx_frames_timeline'
            );

            $table->index(
                ['model_type', 'model_id'],
                'idx_frames_model'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('aggregated_frames', function (Blueprint $table) {
            $table->dropColumn('meet_users_count');

            $table->dropIndex('idx_frames_lookup');
            $table->dropIndex('idx_frames_timeline');
            $table->dropIndex('idx_frames_model');
        });
    }
};
