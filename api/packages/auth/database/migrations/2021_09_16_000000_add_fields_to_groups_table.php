<?php

use Ulams\Core\Migrations\UlamsMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToGroupsTable extends UlamsMigration
{
    public function up()
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('registerable')->default(false);
            $table->foreignId('parent_id')->nullable();
        });
    }

    public function down()
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('parent_id');
            $table->dropColumn('registerable');
        });
    }
}
