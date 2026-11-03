<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tamper-evident audit trail of identity events (plan 4.10, technique of ADR 0034): append-only rows chained
 * by hash, one head row to serialise writers, and (PostgreSQL) a trigger that rejects UPDATE, DELETE and
 * TRUNCATE. Rows outlive providers and users, so there are no foreign keys.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('identity_audit', function (Blueprint $table) {
            $table->bigIncrements('id');
            // user | scim | hris | system | idp
            $table->string('actor', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->uuid('provider_id')->nullable()->index();
            $table->string('action', 48)->index();
            $table->string('subject_type', 32)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->json('data')->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->timestamp('created_at', 6)->index();
            $table->string('prev_hash', 64);
            $table->string('hash', 64);
        });

        Schema::create('identity_audit_head', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->unsignedBigInteger('last_id')->default(0);
            $table->string('last_hash', 64)->default(str_repeat('0', 64));
        });
        DB::table('identity_audit_head')->insert(['id' => 1, 'last_id' => 0, 'last_hash' => str_repeat('0', 64)]);

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION identity_audit_immutable() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'identity_audit is append-only (% is not allowed)', TG_OP USING ERRCODE = 'integrity_constraint_violation';
END;
$$ LANGUAGE plpgsql;
SQL);
            DB::unprepared('CREATE TRIGGER identity_audit_no_change BEFORE UPDATE OR DELETE ON identity_audit FOR EACH ROW EXECUTE FUNCTION identity_audit_immutable()');
            DB::unprepared('CREATE TRIGGER identity_audit_no_truncate BEFORE TRUNCATE ON identity_audit FOR EACH STATEMENT EXECUTE FUNCTION identity_audit_immutable()');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS identity_audit_no_change ON identity_audit');
            DB::unprepared('DROP TRIGGER IF EXISTS identity_audit_no_truncate ON identity_audit');
            DB::unprepared('DROP FUNCTION IF EXISTS identity_audit_immutable()');
        }
        Schema::dropIfExists('identity_audit_head');
        Schema::dropIfExists('identity_audit');
    }
};
