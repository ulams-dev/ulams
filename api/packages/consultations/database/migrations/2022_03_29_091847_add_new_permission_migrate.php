<?php

use EscolaLms\Consultations\Enum\ConsultationsPermissionsEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

class AddNewPermissionMigrate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Permission::findOrCreate(ConsultationsPermissionsEnum::CONSULTATION_CHANGE_TERM, 'api');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Permission::findOrCreate(ConsultationsPermissionsEnum::CONSULTATION_CHANGE_TERM, 'api');
    }
}
