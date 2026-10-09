<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tamper-evident audit trail of Living Course (ADR 0034): append-only rows chained by hash, one
 * head row to serialise writers, and (PostgreSQL) a trigger that rejects UPDATE, DELETE and
 * TRUNCATE. Rows outlive sessions, so there are no foreign keys.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('living_course_audit', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('session_id')->nullable()->index();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            // user | system | agent
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('on_behalf_of')->nullable();
            $table->string('action', 48)->index();
            $table->string('subject_type', 32)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->ulid('source_id')->nullable()->index();
            $table->ulid('revision_id')->nullable();
            $table->string('origin_ref', 128)->nullable();
            $table->unsignedInteger('version_from')->nullable();
            $table->unsignedInteger('version_to')->nullable();
            $table->json('ai_call_ids')->nullable();
            $table->json('data')->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at', 6);
            $table->string('prev_hash', 64);
            $table->string('hash', 64);
        });

        Schema::create('living_course_audit_head', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->unsignedBigInteger('last_id')->default(0);
            $table->string('last_hash', 64)->default(str_repeat('0', 64));
        });
        DB::table('living_course_audit_head')->insert(['id' => 1, 'last_id' => 0, 'last_hash' => str_repeat('0', 64)]);

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION living_course_audit_immutable() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'living_course_audit is append-only (% is not allowed)', TG_OP USING ERRCODE = 'integrity_constraint_violation';
END;
$$ LANGUAGE plpgsql;
SQL);
            DB::unprepared('CREATE TRIGGER living_course_audit_no_change BEFORE UPDATE OR DELETE ON living_course_audit FOR EACH ROW EXECUTE FUNCTION living_course_audit_immutable()');
            DB::unprepared('CREATE TRIGGER living_course_audit_no_truncate BEFORE TRUNCATE ON living_course_audit FOR EACH STATEMENT EXECUTE FUNCTION living_course_audit_immutable()');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS living_course_audit_no_change ON living_course_audit');
            DB::unprepared('DROP TRIGGER IF EXISTS living_course_audit_no_truncate ON living_course_audit');
            DB::unprepared('DROP FUNCTION IF EXISTS living_course_audit_immutable()');
        }
        Schema::dropIfExists('living_course_audit_head');
        Schema::dropIfExists('living_course_audit');
    }
};
