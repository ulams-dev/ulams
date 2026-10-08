<?php

use EscolaLms\Core\Migrations\EscolaMigration;
use EscolaLms\Payments\Enums\Currency;
use EscolaLms\Payments\Enums\PaymentStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends EscolaMigration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id('id');
            $table->timestamps();
            $table->unsignedInteger('amount')->default(0);
            $table->string('currency', 3)->default(Currency::USD);
            $table->string('description', 255)->nullable();
            $table->string('order_id')->nullable();
            $table->string('status')->default(PaymentStatus::NEW);
            $table->nullableMorphs('payable');
            $table->nullableMorphs('billable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
}
