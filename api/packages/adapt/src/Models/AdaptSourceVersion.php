<?php

namespace Ulams\Adapt\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable version of an Adapt JSON source.
 *
 * @property int $adapt_source_id
 * @property int $version
 * @property array $source
 * @property ?string $change_note
 * @property ?int $author_id
 */
class AdaptSourceVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'adapt_source_versions';

    protected $fillable = ['adapt_source_id', 'version', 'source', 'change_note', 'author_id'];

    protected $casts = ['source' => 'array', 'version' => 'integer'];
}
