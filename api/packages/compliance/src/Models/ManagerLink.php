<?php

namespace Ulams\Compliance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ulams\Core\Models\User;

/**
 * @property int $user_id
 * @property int $manager_id
 */
class ManagerLink extends Model
{
    protected $table = 'compliance_manager_links';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
