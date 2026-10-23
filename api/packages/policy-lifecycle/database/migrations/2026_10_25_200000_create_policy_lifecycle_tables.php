<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Policy lifecycle (ADR 0140, plan docs/plans/phase-6-remaining.md 4.3). A policy has versions (the uploaded PDFs) and
 * one variant per department: a Course Builder session with its own copy of the policy source, one LMS course, and the
 * group that must take it. No foreign key points into another package's table except the policy's own, so a package can
 * be removed or reordered without breaking the others; every row is tenant data (separate database per tenant).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->unsignedBigInteger('owner_user_id')->index();
            $table->ulid('current_version_id')->nullable();
            $table->unsignedSmallInteger('minor_due_days')->default(14);
            $table->unsignedSmallInteger('major_due_days')->default(30);
            // totalMinutes, lessonMinutes, language and tone shared by every department
            $table->json('brief');
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::create('policy_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('policy_id');
            $table->foreign('policy_id')->references('id')->on('policies')->cascadeOnDelete();
            $table->unsignedInteger('number');
            // draft | in_review | approved | rejected | superseded
            $table->string('status', 16)->default('draft');
            // initial | minor | major
            $table->string('change_class', 16)->default('initial');
            $table->text('summary')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_sha256', 64);
            $table->unsignedBigInteger('file_size');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamps();
            $table->unique(['policy_id', 'number']);
        });

        Schema::create('policy_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('policy_id');
            $table->foreign('policy_id')->references('id')->on('policies')->cascadeOnDelete();
            $table->string('department_label', 120);
            // the audience: members of this group take the variant course
            $table->unsignedBigInteger('group_id')->nullable()->index();
            // duration, seniority and prior experience of this department
            $table->json('brief');
            $table->ulid('session_id')->nullable()->index();
            $table->ulid('source_id')->nullable();
            // set by "generate"; the variant waits here until the previous department's outline is ready
            $table->timestamp('generation_requested_at')->nullable();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->unsignedBigInteger('path_id')->nullable();
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['policy_id', 'department_label']);
        });

        // Links an issued certificate to the policy version it certified, without touching templates-pdf.
        Schema::create('policy_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fabric_pdf_id')->unique();
            $table->ulid('policy_version_id')->index();
            $table->ulid('variant_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_certificates');
        Schema::dropIfExists('policy_variants');
        Schema::dropIfExists('policy_versions');
        Schema::dropIfExists('policies');
    }
};
