<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tables the learning record store uses. The table names are kept from the earlier
 * implementation, so databases that already have them are left as they are (every table is
 * created only when it is missing) and their records stay readable.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('trax_owners')) {
            Schema::create('trax_owners', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('uuid')->unique();
                $table->string('name')->unique();
                $table->json('meta');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('trax_clients')) {
            Schema::create('trax_clients', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->boolean('active')->default(true);
                $table->json('meta');
                $table->json('permissions');
                $table->boolean('admin')->default(false);
                $table->timestamps();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->foreign('owner_id')->references('id')->on('trax_owners')->onDelete('cascade');
                $table->boolean('visible')->default(true);
                $table->string('category')->nullable();
            });
        }

        if (!Schema::hasTable('trax_basic_http')) {
            Schema::create('trax_basic_http', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('username');
                $table->string('password');
            });
        }

        if (!Schema::hasTable('trax_accesses')) {
            Schema::create('trax_accesses', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->string('cors');
                $table->boolean('active')->default(true);
                $table->json('meta');
                $table->json('permissions');
                $table->boolean('admin')->default(false);
                $table->boolean('inherited_permissions')->default(true);
                $table->timestamps();
                $table->unsignedBigInteger('credentials_id');
                $table->string('credentials_type');
                $table->unsignedBigInteger('client_id');
                $table->foreign('client_id')->references('id')->on('trax_clients')->onDelete('cascade');
                $table->boolean('visible')->default(true);
                $table->string('category')->nullable();
            });
        }

        if (!Schema::hasTable('trax_xapi_statements')) {
            Schema::create('trax_xapi_statements', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('uuid');
                $table->json('data');
                $table->boolean('voided')->default(false)->index();
                $table->timestamps();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->foreign('owner_id')->references('id')->on('trax_owners')->onDelete('cascade');
                $table->unsignedBigInteger('entity_id')->nullable()->index();
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->foreign('client_id')->references('id')->on('trax_clients')->onDelete('restrict');
                $table->unsignedBigInteger('access_id')->nullable()->index();
                $table->foreign('access_id')->references('id')->on('trax_accesses')->onDelete('restrict');
                $table->boolean('pending')->default(false)->index();
                $table->tinyInteger('validation')->default(0)->index();
                $table->unique(['uuid', 'owner_id']);
            });
        }

        if (!Schema::hasTable('trax_xapi_states')) {
            Schema::create('trax_xapi_states', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('state_id', 191);
                $table->string('activity_id', 348)->index();
                $table->string('vid', 191)->index();
                $table->uuid('registration')->nullable();
                $table->json('data');
                $table->string('timestamp');
                $table->timestamps();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->foreign('owner_id')->references('id')->on('trax_owners')->onDelete('cascade');
                $table->unique(['vid', 'activity_id', 'state_id', 'registration', 'owner_id'], 'trax_xapi_states_unique');
            });
        }

        if (!Schema::hasTable('trax_xapi_activity_profiles')) {
            Schema::create('trax_xapi_activity_profiles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('profile_id');
                $table->string('activity_id', 348)->index();
                $table->json('data');
                $table->string('timestamp');
                $table->timestamps();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->foreign('owner_id')->references('id')->on('trax_owners')->onDelete('cascade');
                $table->unique(['activity_id', 'profile_id', 'owner_id'], 'trax_xapi_activity_profiles_unique');
            });
        }

        if (!Schema::hasTable('trax_xapi_agent_profiles')) {
            Schema::create('trax_xapi_agent_profiles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('profile_id');
                $table->string('vid', 191)->index();
                $table->json('data');
                $table->string('timestamp');
                $table->timestamps();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->foreign('owner_id')->references('id')->on('trax_owners')->onDelete('cascade');
                $table->unique(['vid', 'profile_id', 'owner_id']);
            });
        }
    }

    public function down(): void
    {
        // The tables may predate this migration and hold learning records: never dropped here.
    }
};
