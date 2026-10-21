<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One account per provider identity: duplicates (oldest kept) are removed first. */
return new class extends Migration {
    public function up(): void
    {
        DB::statement(
            'DELETE FROM social_accounts WHERE id NOT IN '
            . '(SELECT keep_id FROM (SELECT MIN(id) AS keep_id FROM social_accounts GROUP BY provider, provider_id) AS kept)'
        );

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->unique(['provider', 'provider_id'], 'social_accounts_provider_provider_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropUnique('social_accounts_provider_provider_id_unique');
        });
    }
};
