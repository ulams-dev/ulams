<?php

use EscolaLms\Vouchers\Models\Coupon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCouponEmailsTable extends Migration
{

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupon_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Coupon::class);
            $table->string('email');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('coupon_emails');
    }
}
