<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPublicColumnToLanguageLinesTable extends Migration
{
    public function up(): void
    {
        Schema::table('language_lines', function (Blueprint $table) {
            $table->boolean('public')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('language_lines', function (Blueprint $table) {
            $table->dropColumn('public');
        });
    }
}
