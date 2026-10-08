<?php

use EscolaLms\Vouchers\Models\Category;
use EscolaLms\Vouchers\Models\Coupon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCouponCategoriesTable extends Migration
{

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupons_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Coupon::class);
            $table->foreignIdFor(Category::class);
            $table->boolean('excluded')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('coupons_categories');
    }
}
