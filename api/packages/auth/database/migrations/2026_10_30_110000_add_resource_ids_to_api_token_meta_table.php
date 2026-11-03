<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A scoped token can be limited to named resources (ADR 0194): a BI credential for one saved report cannot read anything
 * else. Null means no limit (every existing token).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('api_token_meta', function (Blueprint $table) {
            $table->json('resource_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('api_token_meta', function (Blueprint $table) {
            $table->dropColumn('resource_ids');
        });
    }
};
