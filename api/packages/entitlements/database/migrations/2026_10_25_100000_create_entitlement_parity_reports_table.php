<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entitlement_parity_reports', function (Blueprint $table) {
            $table->id();
            // `check` (nightly or manual), `backfill`, `dry_run`
            $table->string('kind', 16);
            // `passed` = every user/course pair gives the same access in `course_user` and in the entitlements
            $table->string('status', 8);
            $table->unsignedInteger('pairs_checked')->default(0);
            $table->unsignedInteger('differences')->default(0);
            $table->unsignedInteger('entitlements_created')->default(0);
            // counts per difference kind, a sample of the differing pairs, backfill details
            $table->json('details')->nullable();
            // sha256 over the sorted (user, course, end_date) rows of course_user, and over products_users
            $table->string('snapshot_before', 64)->nullable();
            $table->string('snapshot_after', 64)->nullable();
            $table->timestamps();

            $table->index(['kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlement_parity_reports');
    }
};
