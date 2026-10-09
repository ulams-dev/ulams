<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interactive packages (ADR 0086): a library of uploaded zip packages, their immutable versions
 * (manifest, file list with hashes) and the learners' progress. Files live on the package disk
 * under interactive/<storage_key>/v<version>/.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('interactive_packages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->uuid('storage_key')->unique();
            $table->unsignedInteger('current_version')->default(0);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('interactive_package_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interactive_package_id')->constrained('interactive_packages')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('manifest');
            $table->string('entry');
            // path => {size, sha256}
            $table->json('files');
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->string('licence', 64);
            $table->string('change_note', 500)->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['interactive_package_id', 'version']);
        });

        Schema::create('interactive_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('last_step', 64)->nullable();
            $table->decimal('max_progress', 5, 4)->default(0);
            $table->decimal('score_raw', 8, 2)->nullable();
            $table->decimal('score_max', 8, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['topic_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interactive_progress');
        Schema::dropIfExists('interactive_package_versions');
        Schema::dropIfExists('interactive_packages');
    }
};
