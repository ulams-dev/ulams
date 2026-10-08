<?php

use EscolaLms\Vouchers\Models\Coupon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCouponProductsTable extends Migration
{

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupon_products', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Coupon::class);
            $table->morphs('product');
            $table->boolean('excluded')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('coupon_products');
    }
}
