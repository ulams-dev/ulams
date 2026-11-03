<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A template can be written for one language. Existing rows keep `locale = NULL`: they are the
 * template of no particular language and stay the last fallback when a language has none.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('templates', 'locale')) {
            return;
        }

        Schema::table('templates', function (Blueprint $table) {
            $table->string('locale', 10)->nullable()->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('templates', 'locale')) {
            return;
        }

        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['locale']);
            $table->dropColumn('locale');
        });
    }
};
