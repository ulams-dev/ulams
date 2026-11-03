<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Variant comparison (L2-13): proposed patch versions generated together share a `variant_group`. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_builder_versions', function (Blueprint $table) {
            $table->ulid('variant_group')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('course_builder_versions', function (Blueprint $table) {
            $table->dropColumn('variant_group');
        });
    }
};
