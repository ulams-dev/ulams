<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
class AddBooleanToTypeEnumInSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            DB::statement("ALTER TABLE settings DROP CONSTRAINT settings_type_check");
            DB::statement("ALTER TABLE settings ADD CONSTRAINT settings_type_check CHECK ((type)::text = ANY ((ARRAY['text'::character varying, 'markdown'::character varying, 'json'::character varying, 'image'::character varying, 'file'::character varying, 'config'::character varying, 'boolean'::character varying])::text[]))");
        } else {
            DB::statement("ALTER TABLE settings MODIFY type ENUM('text', 'markdown', 'json', 'image', 'file', 'config', 'boolean') DEFAULT 'text'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            DB::statement("ALTER TABLE settings DROP CONSTRAINT settings_type_check");
            DB::statement("ALTER TABLE settings ADD CONSTRAINT settings_type_check CHECK ((type)::text = ANY ((ARRAY['text'::character varying, 'markdown'::character varying, 'json'::character varying, 'image'::character varying, 'file'::character varying, 'config'::character varying])::text[]))");
        } else {
            DB::statement("ALTER TABLE settings MODIFY type ENUM('text', 'markdown', 'json', 'image', 'file', 'config') DEFAULT 'text'");
        }
    }
}
