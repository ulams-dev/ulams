<?php

namespace EscolaLms\Bookmarks\Models;

use EscolaLms\Bookmarks\Database\Factories\BookmarkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;


/**
 *
 * Class Bookmark
 *
 * @package EscolaLms\Bookmarks\Models
 *
 * @property int $id
 * @property ?string $value
 * @property int $user_id
 * @property string $bookmarkable_type
 * @property int $bookmarkable_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property User $user
 * @property mixed $bookmarkable
 *
 */
class Bookmark extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookmarkable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): BookmarkFactory
    {
        return BookmarkFactory::new();
    }
}
