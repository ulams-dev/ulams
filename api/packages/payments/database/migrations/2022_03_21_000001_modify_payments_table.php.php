<?php

use EscolaLms\Core\Migrations\EscolaMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyPaymentsTable extends EscolaMigration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('driver')->nullable();
            $table->string('gateway_order_id')->nullable();
            $table->renameColumn('billable_id', 'user_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('driver');
            $table->dropColumn('gateway_order_id');
            $table->renameColumn('user_id', 'billable_id');
        });
    }
}
