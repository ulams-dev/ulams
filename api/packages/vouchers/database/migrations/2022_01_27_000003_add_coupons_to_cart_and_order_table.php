<?php

use EscolaLms\Vouchers\Models\Coupon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCouponsToCartAndOrderTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->foreignIdFor(Coupon::class)->nullable();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignIdFor(Coupon::class)->nullable();
            $table->unsignedInteger('discount')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('discount');
            $table->dropColumn('coupon_id');
        });
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('coupon_id');
        });
    }
}
