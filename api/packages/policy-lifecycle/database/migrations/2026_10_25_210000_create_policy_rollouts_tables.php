<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Policy changes (ADR 0140, plan 4.3 steps 6 to 10): a new version is reviewed per department through a Living Course
 * proposal (the rollout row), approved by the owner, applied to the department courses and then rolled out to the people
 * whose certificate certified the previous version. Rollout and reassignment rows make every step idempotent.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('policy_rollouts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('policy_version_id');
            $table->foreign('policy_version_id')->references('id')->on('policy_versions')->cascadeOnDelete();
            $table->ulid('variant_id');
            // the Living Course proposal of this department (null when no course element cites a changed section)
            $table->ulid('proposal_id')->nullable()->index();
            // pending | no_change | ready | applying | applied | failed | rejected
            $table->string('status', 16)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('rolled_out_at')->nullable();
            $table->timestamps();
            $table->unique(['policy_version_id', 'variant_id']);
        });

        Schema::create('policy_reassignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('rollout_id');
            $table->foreign('rollout_id')->references('id')->on('policy_rollouts')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            // exam (minor change) | course (major change)
            $table->string('kind', 8);
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->unsignedBigInteger('revoked_certificate_id')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['rollout_id', 'user_id']);
        });

        Schema::table('policy_versions', function (Blueprint $table) {
            // what the proposals suggested, and why the owner chose another class (required to lower it)
            $table->string('suggested_class', 16)->nullable();
            $table->text('class_reason')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamp('rolled_out_at')->nullable();
        });

        Schema::table('policy_variants', function (Blueprint $table) {
            // the exam-only course (the department course's final test) and the hidden builder session that holds it
            $table->unsignedBigInteger('exam_course_id')->nullable()->index();
            $table->ulid('exam_session_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('policy_variants', function (Blueprint $table) {
            $table->dropColumn(['exam_course_id', 'exam_session_id']);
        });
        Schema::table('policy_versions', function (Blueprint $table) {
            $table->dropColumn(['suggested_class', 'class_reason', 'rejected_by', 'reject_reason', 'rolled_out_at']);
        });
        Schema::dropIfExists('policy_reassignments');
        Schema::dropIfExists('policy_rollouts');
    }
};
