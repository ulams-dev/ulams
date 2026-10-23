<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A department can have an academy of its own (ADR 0141, amendment): a separate tenant created from a template, where the
 * department's reviewed course is applied again. The state of that request is kept on the variant, as the builder keeps
 * the state of a "new site" on its session.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('policy_variants', function (Blueprint $table) {
            $table->json('academy')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('policy_variants', function (Blueprint $table) {
            $table->dropColumn('academy');
        });
    }
};
