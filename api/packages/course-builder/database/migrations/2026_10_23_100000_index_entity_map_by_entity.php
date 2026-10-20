<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reverse lookup (LMS entity to blueprint element) for learner-insights (ADR 0057): the map was
 * only ever read by session and element.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_builder_entity_map', function (Blueprint $table) {
            $table->index(['entity_type', 'entity_id'], 'cb_entity_map_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::table('course_builder_entity_map', function (Blueprint $table) {
            $table->dropIndex('cb_entity_map_entity_idx');
        });
    }
};
