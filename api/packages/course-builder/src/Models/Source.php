<?php

namespace Ulams\CourseBuilder\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An uploaded source file (kept privately) and its normalised Source Document.
 *
 * @property string $id
 * @property string $session_id
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property string $sha256
 * @property string $path
 * @property string $status uploaded | processing | ready | failed
 * @property string|null $markdown_path
 * @property array|null $metadata
 * @property int $token_estimate
 * @property string|null $error
 */
class Source extends Model
{
    use HasUlids;

    protected $table = 'course_builder_sources';

    protected $guarded = [];

    protected $casts = ['metadata' => 'array', 'size' => 'integer', 'token_estimate' => 'integer'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function fragments(): HasMany
    {
        return $this->hasMany(Fragment::class, 'source_id')->orderBy('ordinal');
    }

    public function kind(): string
    {
        return match (true) {
            str_contains($this->mime, 'pdf') => 'pdf',
            str_contains($this->mime, 'wordprocessingml') || str_ends_with(strtolower($this->original_name), '.docx') => 'docx',
            default => 'markdown',
        };
    }
}
