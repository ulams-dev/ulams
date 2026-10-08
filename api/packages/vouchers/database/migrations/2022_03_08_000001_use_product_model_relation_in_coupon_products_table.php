<?php

use EscolaLms\Cart\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UseProductModelRelationInCouponProductsTable extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_products', function (Blueprint $table) {
            $table->dropMorphs('product');
        });
        Schema::rename('coupon_products', 'coupons_products');
        Schema::table('coupons_products', function (Blueprint $table) {
            $table->foreignIdFor(Product::class);
        });
    }

    public function down(): void
    {
        Schema::table('coupons_products', function (Blueprint $table) {
            $table->dropColumn('product_id');
        });
        Schema::rename('coupons_products', 'coupon_products');
        Schema::table('coupon_products', function (Blueprint $table) {
            $table->morphs('product');
        });
    }
}
