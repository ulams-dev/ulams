<?php

namespace Ulams\Reporting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A report definition with a name, an owner and the roles it is shared with.
 *
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property int $owner_user_id
 * @property array<string,mixed> $definition
 * @property int $definition_version
 * @property string $visibility
 * @property list<string>|null $shared_roles
 * @property \Illuminate\Support\Carbon|null $last_run_at
 */
class SavedReport extends Model
{
    use HasUlids;
    use SoftDeletes;

    public const PRIVATE = 'private';
    public const ROLES = 'roles';

    protected $table = 'saved_reports';

    protected $guarded = [];

    protected $casts = ['definition' => 'array', 'shared_roles' => 'array', 'definition_version' => 'integer', 'owner_user_id' => 'integer', 'last_run_at' => 'datetime'];
}
