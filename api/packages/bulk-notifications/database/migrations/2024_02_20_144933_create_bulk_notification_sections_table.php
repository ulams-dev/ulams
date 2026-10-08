<?php

use EscolaLms\BulkNotifications\Models\BulkNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBulkNotificationSectionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_notification_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value');
            $table->foreignIdFor(BulkNotification::class)->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_notification_sections');
    }
}
