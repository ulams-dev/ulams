<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_paths', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // An ordered course in a path; `prerequisites` lists the course ids of the same path that must be done first.
        Schema::create('compliance_path_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('path_id');
            $table->unsignedBigInteger('course_id');
            $table->foreign('path_id')->references('id')->on('compliance_paths')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->json('prerequisites')->nullable();
            $table->unique(['path_id', 'course_id']);
        });

        Schema::create('compliance_path_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('path_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('path_id')->references('id')->on('compliance_paths')->cascadeOnDelete();
            $table->string('source', 16)->default('manual');
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'path_id']);
        });

        Schema::create('compliance_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->boolean('active')->default(true);
            $table->json('conditions');
            $table->unsignedBigInteger('path_id');
            $table->foreign('path_id')->references('id')->on('compliance_paths')->cascadeOnDelete();
            $table->unsignedInteger('due_days')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_rules');
        Schema::dropIfExists('compliance_path_enrollments');
        Schema::dropIfExists('compliance_path_steps');
        Schema::dropIfExists('compliance_paths');
    }
};
