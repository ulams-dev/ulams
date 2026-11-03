<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source updates reach every variant of a course as reviewable proposals (ADR 0192, CV-5): one proposal per branch, linked by a
 * `proposal_group`. `variant_id` is the variant a proposal is for; null is the base course.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('living_course_proposals', function (Blueprint $table) {
            $table->ulid('variant_id')->nullable()->index();
            $table->ulid('proposal_group')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('living_course_proposals', function (Blueprint $table) {
            $table->dropColumn(['variant_id', 'proposal_group']);
        });
    }
};
