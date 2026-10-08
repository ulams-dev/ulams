<?php

use EscolaLms\Vouchers\Models\Coupon;
use EscolaLms\Vouchers\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCouponUserTable extends Migration
{

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupon_user', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Coupon::class);
            $table->foreignIdFor(User::class);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('coupon_user');
    }
}
