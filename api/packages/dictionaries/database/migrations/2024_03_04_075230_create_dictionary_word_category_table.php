<?php

use EscolaLms\Dictionaries\Models\DictionaryWord;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictionary_word_category', function (Blueprint $table) {
            $table->foreignIdFor(DictionaryWord::class)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('category_id');
            $table->foreign('category_id')->on('categories')->references('id')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictionary_word_category');
    }
};
