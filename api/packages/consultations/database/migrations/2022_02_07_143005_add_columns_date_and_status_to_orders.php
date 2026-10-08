<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsDateAndStatusToOrders extends Migration
{

    public function up(): void
    {
        if (
            Schema::hasTable('orders') &&
            !Schema::hasColumns('orders', ['executed_at', 'executed_status'])
        ) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dateTime('executed_at')->nullable();
                $table->string('executed_status')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('orders') &&
            Schema::hasColumns('orders', ['executed_at', 'executed_status'])
        ) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn(['executed_at', 'executed_status']);
            });
        }
    }
}
