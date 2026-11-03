<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only learner signal stream (ADR 0057). Runs in the tenant database. Idempotent: a
 * re-run on a tenant that already has the table does nothing.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('learner_signals')) {
            return;
        }

        Schema::create('learner_signals', function (Blueprint $table) {
            $table->id();
            // no foreign key: users are soft-deleted and erasure is explicit (retention and erase services)
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('topic_id')->nullable();
            $table->string('element_key', 64)->nullable();
            $table->char('element_id', 26)->nullable();
            $table->char('session_id', 26)->nullable();
            $table->string('type', 40);
            $table->decimal('value', 10, 4)->nullable();
            $table->json('data')->nullable();
            $table->string('source', 20);
            $table->string('source_ref', 80);
            $table->timestamp('occurred_at', 3)->index();
            $table->timestamp('recorded_at', 3);

            $table->unique(['source', 'source_ref', 'type'], 'learner_signals_idempotency');
            $table->index(['user_id', 'course_id', 'occurred_at'], 'learner_signals_user_course_time');
            $table->index(['course_id', 'element_key', 'occurred_at'], 'learner_signals_course_element_time');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION learner_signals_reject_update() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'learner_signals is append-only';
END;
$$ LANGUAGE plpgsql
SQL);
            DB::unprepared('CREATE TRIGGER learner_signals_no_update BEFORE UPDATE ON learner_signals FOR EACH ROW EXECUTE FUNCTION learner_signals_reject_update()');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS learner_signals_no_update ON learner_signals');
            DB::unprepared('DROP FUNCTION IF EXISTS learner_signals_reject_update()');
        }
        Schema::dropIfExists('learner_signals');
    }
};
