<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Webhook deliveries: duplicate detection and a short diagnostic log. Payloads are never stored, only their digest. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('living_course_webhook_deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('connection_id');
            $table->string('delivery_id', 100);
            $table->string('event', 32)->nullable();
            $table->boolean('signature_valid')->default(true);
            // queued | ignored | duplicate | rejected | dropped
            $table->string('outcome', 16);
            $table->string('payload_sha256', 64);
            $table->timestamp('received_at')->useCurrent()->index();
            $table->unique(['connection_id', 'delivery_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('living_course_webhook_deliveries');
    }
};
