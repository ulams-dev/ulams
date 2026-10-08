<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPivotTableForOrderItemsAndUser extends Migration
{

    public function up(): void
    {
        if (
            Schema::hasTable('orders') &&
            Schema::hasColumns('orders', ['executed_at', 'executed_status'])
        ) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn(['executed_at', 'executed_status']);
            });
        }
        Schema::create('consultation_terms', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('order_item_id')->unsigned();
            $table->dateTime('executed_at')->nullable();
            $table->string('executed_status')->nullable();
            $table->timestamps();
            if (Schema::hasTable('orders')) {
                $table->foreign('order_item_id')->on('order_items')->references('id')->cascadeOnDelete();
            }
            $table->foreign('user_id')->on('users')->references('id');
        });
    }

    public function down(): void
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

        Schema::dropIfExists('consultation_terms');
    }
}
