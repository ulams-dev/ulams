<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commerce_product_links', function (Blueprint $table) {
            $table->id();
            $table->string('sellable_type', 32);
            $table->unsignedBigInteger('sellable_id');
            $table->string('provider', 32);
            $table->string('external_id', 191);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->boolean('active')->default(false);
            $table->timestamps();

            $table->unique(['sellable_type', 'sellable_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_product_links');
    }
};
