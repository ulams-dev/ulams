<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAdditionalColumnsToConsultationAccessEnquiriesTable extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_access_enquiries', function (Blueprint $table) {
            $table->nullableMorphs('related');
            $table->string('title')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('consultation_access_enquiries', function (Blueprint $table) {
            $table->dropMorphs('related');
            $table->dropColumn('title');
        });
    }
}
