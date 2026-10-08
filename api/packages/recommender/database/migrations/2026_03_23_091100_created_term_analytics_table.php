<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('term_analytics', function (Blueprint $table) {
            $table->id();

            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->dateTime('term');

            $table->unsignedInteger('count')->default(0);

            $table->decimal('sum_attention', 30, 25)->nullable();
            $table->decimal('sum_emotions_angry', 30, 25)->nullable();
            $table->decimal('sum_emotions_disgusted', 30, 25)->nullable();
            $table->decimal('sum_emotions_fearful', 30, 25)->nullable();
            $table->decimal('sum_emotions_happy', 30, 25)->nullable();
            $table->decimal('sum_emotions_neutral', 30, 25)->nullable();
            $table->decimal('sum_emotions_sad', 30, 25)->nullable();
            $table->decimal('sum_emotions_surprised', 30, 25)->nullable();

            $table->decimal('avg_attention', 30, 25)->nullable();
            $table->decimal('avg_emotions_angry', 30, 25)->nullable();
            $table->decimal('avg_emotions_disgusted', 30, 25)->nullable();
            $table->decimal('avg_emotions_fearful', 30, 25)->nullable();
            $table->decimal('avg_emotions_happy', 30, 25)->nullable();
            $table->decimal('avg_emotions_neutral', 30, 25)->nullable();
            $table->decimal('avg_emotions_sad', 30, 25)->nullable();
            $table->decimal('avg_emotions_surprised', 30, 25)->nullable();

            $table->unsignedInteger('aggregated_frames_count')->default(0);
            $table->timestamp('last_frame_at')->nullable();

            $table->string('max_emotion')->nullable();
            $table->decimal('max_emotion_value', 30, 25)->nullable();

            $table->timestamps();

            $table->unique(['model_type', 'model_id', 'term']);
            $table->index(['model_type', 'model_id']);
            $table->index(['model_type', 'term']);
        });

        Schema::table('aggregated_frames', function (Blueprint $table) {
            $table->foreignId('term_analytic_id')
                ->nullable()
                ->references('id')
                ->on('term_analytics')
                ->nullOnDelete();

            $table->index(
                ['model_type', 'model_id', 'term', 'term_analytic_id'],
                'idx_frames_term_analytics'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('aggregated_frames', function (Blueprint $table) {
            $table->dropColumn('term_analytic_id');
        });

        Schema::dropIfExists('term_analytics');
    }
};
