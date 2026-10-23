<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Style profiles (ADR 0196, SG-1): the typed, cited and author-approved reading of an uploaded style guide.
 *
 * - `scope_type` / `scope_id`: `tenant` (null id) is the academy default; `entry_point` and `brief` overrides come
 *   with SG-3. One approved profile per scope; approving a new one supersedes the old.
 * - `source_id` / `session_id`: the style guide source and the holder session its fragments live in (a session of
 *   status `style_guide`, never listed and never used to generate a course), so citations resolve like any other.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_builder_style_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('scope_type', 16)->default('tenant');
            $table->string('scope_id', 64)->nullable();
            $table->string('name');
            $table->ulid('source_id')->nullable()->index();
            $table->ulid('session_id')->nullable()->index();
            $table->json('profile');
            $table->json('warnings')->nullable();
            $table->string('status', 16)->default('suggested');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['scope_type', 'scope_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_builder_style_profiles');
    }
};
