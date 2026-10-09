<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_calls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('task', 64);
            $table->string('prompt_id', 128)->nullable();
            $table->unsignedInteger('prompt_version')->nullable();
            $table->string('profile', 32)->nullable();
            $table->string('driver', 32);
            $table->string('model_requested', 128);
            $table->string('model_served', 128)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_creation_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedBigInteger('cost_micro_usd')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('stop_reason', 32)->nullable();
            // ok | invalid | refused | max_tokens | error
            $table->string('status', 16);
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->string('request_id', 128)->nullable();
            $table->text('error')->nullable();
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_calls');
    }
};
