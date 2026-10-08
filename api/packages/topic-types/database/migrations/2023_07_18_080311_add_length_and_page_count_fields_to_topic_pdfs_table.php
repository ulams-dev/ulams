<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLengthAndPageCountFieldsToTopicPdfsTable extends Migration
{
    public function up(): void
    {
        Schema::table('topic_pdfs', function (Blueprint $table) {
            $table->bigInteger('length')->nullable();
            $table->integer('page_count')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('topic_pdfs', function (Blueprint $table) {
            $table->dropColumn([
                'length',
                'page_count',
            ]);
        });
    }
}
