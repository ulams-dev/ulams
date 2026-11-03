<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('dataset', 32);
            $table->json('filters')->nullable();
            $table->string('frequency', 16); // daily, weekly, monthly
            $table->string('format', 8)->default('csv');
            $table->json('recipient_ids'); // tenant users who receive the file
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_report_schedules');
    }
};
