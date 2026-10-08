<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveUniqueIndexInConsultaionUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (DB::connection() instanceof MySqlConnection) {
            DB::statement('ALTER TABLE `consultation_user` DROP INDEX `consultation_user_unique`, ADD INDEX (user_id, consultation_id)');
        } else {
            Schema::table('consultation_user', function (Blueprint $table) {
                $table->dropUnique('consultation_user_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('consultation_user', function (Blueprint $table) {
            $table->unique(['user_id', 'consultation_id'], 'consultation_user_unique');
        });
    }
}
