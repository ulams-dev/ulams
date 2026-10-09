<?php

namespace Ulams\LiaScript\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable version of a LiaScript document.
 *
 * @property int $id
 * @property int $liascript_document_id
 * @property int $version
 * @property string $markdown
 * @property array $assets
 * @property ?string $change_note
 * @property ?int $author_id
 * @property ?int $restored_from
 */
class LiaScriptVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'liascript_versions';

    protected $fillable = [
        'liascript_document_id', 'version', 'markdown', 'assets', 'change_note', 'author_id', 'restored_from',
    ];

    protected $casts = [
        'assets' => 'array',
        'version' => 'integer',
    ];

    protected $hidden = ['markdown'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(LiaScriptDocument::class, 'liascript_document_id');
    }

    public function summary(): array
    {
        return [
            'version' => $this->version,
            'change_note' => $this->change_note,
            'author_id' => $this->author_id,
            'restored_from' => $this->restored_from,
            'size' => strlen($this->markdown),
            'assets' => array_keys($this->assets ?? []),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
