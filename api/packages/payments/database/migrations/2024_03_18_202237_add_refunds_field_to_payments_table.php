<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRefundsFieldToPaymentsTable extends Migration
{

    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('refund')->nullable();
            $table->string('gateway_request_id')->nullable();
            $table->string('gateway_refunds_uuid')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('refund');
            $table->dropColumn('gateway_request_id');
            $table->dropColumn('gateway_refunds_uuid');
        });
    }
}
