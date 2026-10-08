<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsultationAccessEnquiryProposedTermsTable extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_access_enquiry_proposed_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consultation_access_enquiry_id');
            $table->foreign('consultation_access_enquiry_id', 'cons_acc_enq_prop_terms_cons_acc_enq_id_foreign')
                ->references('id')
                ->on('consultation_access_enquiries')
                ->onDelete('cascade');
            $table->dateTime('proposed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_access_enquiry_proposed_terms');
    }
}
