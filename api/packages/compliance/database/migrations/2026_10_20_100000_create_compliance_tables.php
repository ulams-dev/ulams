<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_course_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id')->unique();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->boolean('mandatory')->default(false);
            $table->unsignedInteger('validity_days')->nullable();
            $table->unsignedInteger('recertify_before_days')->nullable();
            $table->unsignedInteger('default_due_days')->nullable();
            $table->json('reminder_days')->nullable();
            $table->json('escalation_days')->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->string('source', 32)->default('manual');
            $table->string('status', 16)->default('assigned');
            $table->timestamp('assigned_at')->useCurrent();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('certificate_id')->nullable();
            $table->unsignedBigInteger('recertifies_certificate_id')->nullable()->unique();
            $table->unsignedSmallInteger('escalation_level')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'course_id']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('compliance_manager_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('manager_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('manager_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Append-only: one row per notification sent, the unique key makes reruns send nothing twice.
        Schema::create('compliance_notification_log', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 48);
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->integer('offset_days')->default(0);
            $table->unsignedBigInteger('user_id');
            $table->timestamp('sent_at')->useCurrent();
            $table->unique(['kind', 'subject_type', 'subject_id', 'offset_days', 'user_id'], 'compliance_notification_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_notification_log');
        Schema::dropIfExists('compliance_manager_links');
        Schema::dropIfExists('compliance_assignments');
        Schema::dropIfExists('compliance_course_settings');
    }
};
