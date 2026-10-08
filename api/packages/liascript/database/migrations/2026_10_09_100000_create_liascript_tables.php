<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LiaScript course sources (spec 1.1): Markdown plus assets, versioned. The Markdown is the
 * source of truth (text form for the AI phases); versions are never edited, a change or a
 * restore adds a version.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('liascript_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('current_version')->default(1);
            $table->unsignedBigInteger('author_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('liascript_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liascript_document_id')->constrained('liascript_documents')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('markdown');
            // relative path in the Markdown => {path on the disk, size, sha256}
            $table->json('assets');
            $table->string('change_note', 500)->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->unsignedInteger('restored_from')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['liascript_document_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liascript_versions');
        Schema::dropIfExists('liascript_documents');
    }
};
