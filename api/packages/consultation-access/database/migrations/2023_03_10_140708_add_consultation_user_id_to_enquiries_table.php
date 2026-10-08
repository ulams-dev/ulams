<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConsultationUserIdToEnquiriesTable extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_access_enquiries', function (Blueprint $table) {
            $table->foreignId('consultation_user_id')
                ->nullable()
                ->constrained('consultation_user')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consultation_access_enquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consultation_user_id');
        });
    }
}
