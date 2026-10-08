<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeValueFieldNullableInBookmarksTable extends Migration
{
    public function up(): void
    {
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->text('value')->change()->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->text('value')->change()->nullable(false);
        });
    }
}
