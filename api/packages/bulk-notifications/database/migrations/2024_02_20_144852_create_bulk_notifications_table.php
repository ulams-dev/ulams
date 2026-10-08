<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBulkNotificationsTable extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('channel');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_notifications');
    }
}
