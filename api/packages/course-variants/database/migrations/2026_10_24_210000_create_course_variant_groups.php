<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Families of courses that are variants of one course (ADR 0192). Tenant database; no tenant column. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_variant_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('default_course_id')->unique();
            $table->timestamps();
        });

        Schema::create('course_variant_group_members', function (Blueprint $table) {
            $table->id();
            $table->ulid('group_id')->index();
            $table->unsignedBigInteger('course_id')->unique();
            $table->string('variant_key', 64);
            $table->string('label');
            $table->string('level', 16)->nullable();
            $table->string('role', 64)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['group_id', 'variant_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_variant_group_members');
        Schema::dropIfExists('course_variant_groups');
    }
};
