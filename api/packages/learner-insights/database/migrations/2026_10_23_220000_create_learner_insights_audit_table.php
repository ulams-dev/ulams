<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What was exported or erased, by whom and how much: counts only, never content (ADR 0061). */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('learner_insights_audit')) {
            return;
        }
        Schema::create('learner_insights_audit', function (Blueprint $table) {
            $table->id();
            $table->string('action', 40);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('subject_user_id')->index();
            $table->json('counts')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_insights_audit');
    }
};
