<?php

namespace EscolaLms\PencilSpaces\Models;

use EscolaLms\PencilSpaces\Database\Factories\PencilSpaceAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class PencilSpaceAccount
 *
 * @package EscolaLms\PencilSpaces\Models
 *
 * @property int $id
 * @property string $pencil_space_id
 * @property string $pencil_space_email
 * @property Carbon $created_at
 * @property Carbon $update_at
 *
 * @property-read User $user
 */
class PencilSpaceAccount extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): PencilSpaceAccountFactory
    {
        return PencilSpaceAccountFactory::new();
    }
}
