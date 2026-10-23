<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Learner routing for a family of course variants (ADR 0192, CV-5): settings per family, who is in which variant and
 * why, assignment rules, the placement check and the hints to switch. Tenant database; no tenant column.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_variant_groups', function (Blueprint $table) {
            // how the default course is shown in the picker (the builder sets them; null falls back to the course title)
            $table->string('default_label')->nullable();
            $table->string('default_level', 16)->nullable();
            $table->string('default_role', 64)->nullable();
            // mandatory training: when set, only finishing this course of the family counts (otherwise any variant does)
            $table->unsignedBigInteger('pinned_course_id')->nullable();
            // a variant assigned by an admin or a rule cannot be changed by the learner
            $table->boolean('lock_assigned')->default(false);
            // the optional placement check (advisory only) and the hints to switch variant
            $table->boolean('placement_enabled')->default(false);
            $table->boolean('hints_enabled')->default(true);
        });

        Schema::table('course_variant_group_members', function (Blueprint $table) {
            // a retired variant is not offered to new learners; those in it keep it
            $table->timestamp('retired_at')->nullable();
        });

        Schema::create('course_variant_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->ulid('group_id');
            $table->unsignedBigInteger('course_id');
            $table->string('source', 16); // self | placement | admin | rule | hint
            $table->json('reason')->nullable(); // {key, params}, rendered by the learner's app
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->timestamp('decided_at');
            $table->timestamp('superseded_at')->nullable();
            // true on the current row only and null on history, so the unique key holds one current row per learner and family
            // (NULLs are distinct on PostgreSQL and MySQL)
            $table->boolean('is_current')->nullable();
            $table->unique(['user_id', 'group_id', 'is_current'], 'course_variant_assignments_current');
            $table->index(['group_id', 'course_id']);
            $table->timestamps();
        });

        Schema::create('course_variant_rules', function (Blueprint $table) {
            $table->id();
            $table->ulid('group_id')->index();
            $table->unsignedBigInteger('course_id'); // the variant course the rule assigns
            $table->string('name');
            $table->json('conditions');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('course_variant_placement_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('group_id');
            $table->string('level', 16);
            $table->string('element_id', 26);
            $table->unsignedBigInteger('source_course_id');
            $table->json('question'); // type, stem, options (id, text, correct), explanation, citations, objectiveIds
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['group_id', 'element_id']);
        });

        Schema::create('course_variant_placement_attempts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->ulid('group_id');
            $table->json('scores'); // level => {correct, total}
            $table->unsignedBigInteger('suggested_course_id')->nullable();
            $table->json('reason');
            $table->timestamp('created_at');
            $table->index(['user_id', 'group_id']);
        });

        Schema::create('course_variant_hints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->ulid('group_id');
            $table->unsignedBigInteger('course_id'); // the variant the learner was in
            $table->unsignedBigInteger('suggested_course_id');
            $table->string('direction', 8); // easier | harder
            $table->json('reasons');
            $table->string('status', 12)->default('shown'); // shown | accepted | dismissed | expired
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'group_id']);
        });
    }

    public function down(): void
    {
        foreach (['course_variant_hints', 'course_variant_placement_attempts', 'course_variant_placement_items', 'course_variant_rules', 'course_variant_assignments'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('course_variant_group_members', function (Blueprint $table) {
            $table->dropColumn('retired_at');
        });
        Schema::table('course_variant_groups', function (Blueprint $table) {
            $table->dropColumn(['default_label', 'default_level', 'default_role', 'pinned_course_id', 'lock_assigned', 'placement_enabled', 'hints_enabled']);
        });
    }
};
