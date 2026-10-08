<?php

use Ulams\Core\Migrations\UlamsMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRedirectUrlToPaymentsTable extends UlamsMigration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('redirect_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('redirect_url');
        });
    }
}
