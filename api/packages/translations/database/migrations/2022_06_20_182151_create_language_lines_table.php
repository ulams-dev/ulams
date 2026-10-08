<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class CreateLanguageLinesTable extends Migration
{
    public function up(): void
    {
        Schema::create('language_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('group')->index();
            $table->string('key');
            $table->text('text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('language_lines');
    }
}
