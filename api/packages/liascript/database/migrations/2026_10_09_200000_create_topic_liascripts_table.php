<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Topic type LiaScript: a topic plays the current version of a LiaScript document.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('topic_liascripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('value')->constrained('liascript_documents')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_liascripts');
    }
};
