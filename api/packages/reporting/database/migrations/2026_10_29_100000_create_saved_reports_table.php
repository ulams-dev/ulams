<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A saved report is a definition, not data (tenant database, ADR 0194): who may see it is the owner and the roles it is
 * shared with, and what a viewer gets from it is decided by their own permissions every time it runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('owner_user_id')->index();
            $table->json('definition');
            $table->unsignedSmallInteger('definition_version')->default(1);
            // private (the owner and administrators who manage reports) | roles (also everyone with one of shared_roles)
            $table->string('visibility', 16)->default('private');
            $table->json('shared_roles')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
