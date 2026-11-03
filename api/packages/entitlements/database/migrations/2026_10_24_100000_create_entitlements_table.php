<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entitlements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // nullable only while a seat invite is pending (seat packages, planned CM-9)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('status', 16)->default('active');
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->string('source_type', 32);
            // one grant per source: `sylius:<channel>:order_item:<id>`, `migration:course_user:<course>:<user>`, ...
            $table->string('source_key', 191)->unique();
            $table->json('source_ref')->nullable();
            // reserved for seat packages (CM-9); the seat_pools table does not exist yet
            $table->unsignedBigInteger('seat_pool_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 64)->nullable();
            $table->string('granted_by', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'subject_type', 'subject_id', 'status'], 'entitlements_user_subject_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlements');
    }
};
