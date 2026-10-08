<?php

use EscolaLms\BulkNotifications\Models\BulkNotification;
use EscolaLms\BulkNotifications\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBulkNotificationUserTable extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_notification_user', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(BulkNotification::class)->constrained();
            $table->foreignIdFor(User::class)->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_notification_user');
    }
}
