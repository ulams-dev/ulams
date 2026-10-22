<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Improvement proposals from learner insights are proposals of their own kind (ADR 0181): no source
 * revision on either side, and the aggregated evidence that motivated them.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('living_course_proposals', function (Blueprint $table) {
            // source | insights
            $table->string('kind', 16)->default('source')->after('number');
            $table->json('evidence')->nullable()->after('counts');
            $table->ulid('source_id')->nullable()->change();
            $table->ulid('from_revision_id')->nullable()->change();
            $table->ulid('to_revision_id')->nullable()->change();
            $table->index(['session_id', 'kind', 'status'], 'living_course_proposals_session_kind_status');
        });
    }

    public function down(): void
    {
        if (DB::table('living_course_proposals')->where('kind', '!=', 'source')->exists()) {
            throw new RuntimeException('Cannot roll back: improvement proposals from learner insights exist and have no source revision.');
        }
        Schema::table('living_course_proposals', function (Blueprint $table) {
            $table->dropIndex('living_course_proposals_session_kind_status');
            $table->dropColumn(['kind', 'evidence']);
            $table->ulid('source_id')->nullable(false)->change();
            $table->ulid('from_revision_id')->nullable(false)->change();
            $table->ulid('to_revision_id')->nullable(false)->change();
        });
    }
};
