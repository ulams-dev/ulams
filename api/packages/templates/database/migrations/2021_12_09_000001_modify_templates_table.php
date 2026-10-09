<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyTemplatesTable extends Migration
{
    private string $table = 'templates';

    public function up()
    {
        // Laravel 11 changes columns natively and emits the column comment after the whole
        // blueprint, so the type change must run before the rename, in its own statement.
        if (!Schema::hasColumn($this->table, 'channel') && Schema::hasColumn($this->table, 'vars_set')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->string('vars_set')->default(null)->change();
            });
        }

        Schema::table(
            $this->table,
            function (Blueprint $table) {
                if (!Schema::hasColumn($this->table, 'event')) {
                    if (Schema::hasColumn($this->table, 'type')) {
                        $table->renameColumn('type', 'event');
                    } else {
                        $table->string('event');
                    }
                } elseif (Schema::hasColumn($this->table, 'type')) {
                    $table->dropColumn('type');
                }

                if (!Schema::hasColumn($this->table, 'channel')) {
                    if (Schema::hasColumn($this->table, 'vars_set')) {
                        $table->renameColumn('vars_set', 'channel');
                    } else {
                        $table->string('channel');
                    }
                } elseif (Schema::hasColumn($this->table, 'vars_set')) {
                    $table->dropColumn('vars_set');
                }

                if (!Schema::hasColumn($this->table, 'assignable_id')) {
                    $table->nullableMorphs('assignable');
                }

                if (!Schema::hasColumn($this->table, 'default')) {
                    $table->boolean('default')->default(false);
                }

                if (Schema::hasColumn($this->table, 'content')) {
                    $table->dropColumn('content');
                }
            }
        );
    }

    public function down()
    {
        Schema::table(
            $this->table,
            function (Blueprint $table) {
                if (!Schema::hasColumn($this->table, 'type')) {
                    if (Schema::hasColumn($this->table, 'event')) {
                        $table->renameColumn('event', 'type');
                    } else {
                        $table->string('type');
                    }
                } elseif (Schema::hasColumn($this->table, 'event')) {
                    $table->dropColumn('event');
                }

                if (!Schema::hasColumn($this->table, 'vars_set')) {
                    if (Schema::hasColumn($this->table, 'channel')) {
                        $table->renameColumn('channel', 'vars_set');
                    } else {
                        $table->string('vars_set');
                    }
                } elseif (Schema::hasColumn($this->table, 'channel')) {
                    $table->dropColumn('channel');
                }

                if (!Schema::hasColumn($this->table, 'content')) {
                    $table->longText('content');
                }

                if (Schema::hasColumn($this->table, 'default')) {
                    $table->dropColumn('default');
                }

                if (Schema::hasColumn($this->table, 'assignable_id')) {
                    $table->dropMorphs('assignable');
                }
            }
        );
    }
}
