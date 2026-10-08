<?php

use EscolaLms\Dictionaries\Models\Dictionary;
use EscolaLms\Dictionaries\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictionary_user', function (Blueprint $table) {
            $table->foreignIdFor(Dictionary::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->dateTime('end_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictionary_word_user');
    }
};
