<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('consultation_access_enquiries', function (Blueprint $table) {
            $table->foreignId('consultation_user_term_id')
                ->nullable()
                ->constrained('consultation_user_terms')
                ->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::table('consultation_access_enquiries', function (Blueprint $table) {
            $table->dropColumn('consultation_user_term_id');
        });
    }
};
