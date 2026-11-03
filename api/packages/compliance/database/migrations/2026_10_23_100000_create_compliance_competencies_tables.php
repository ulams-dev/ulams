<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The competency framework: a tree (area > skill), one per tenant.
        Schema::create('compliance_competencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->foreign('parent_id')->references('id')->on('compliance_competencies')->nullOnDelete();
            $table->string('name', 255);
            $table->string('code', 64)->nullable()->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // A competency a course teaches. AI suggestions stay `suggested` until the author confirms them.
        Schema::create('compliance_course_competencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('competency_id');
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('competency_id')->references('id')->on('compliance_competencies')->cascadeOnDelete();
            $table->string('status', 16)->default('confirmed'); // suggested, confirmed
            $table->string('source', 16)->default('author'); // author, ai
            $table->text('rationale')->nullable();
            $table->json('lesson_ids')->nullable(); // the lessons the suggestion points at
            $table->string('ai_call_id', 40)->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'competency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_course_competencies');
        Schema::dropIfExists('compliance_competencies');
    }
};
