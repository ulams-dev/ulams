<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('knowledge_exports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('scope', 16); // course | tenant
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->string('status', 16)->index(); // queued | running | ready | failed
            $table->string('trigger', 16)->default('manual'); // manual | auto
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('pack_hash', 64)->nullable();
            $table->unsignedInteger('courses')->default(0);
            $table->unsignedInteger('chunks')->default(0);
            $table->unsignedInteger('uncited')->default(0);
            $table->string('directory')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['scope', 'course_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_exports');
    }
};
