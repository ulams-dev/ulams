<?php

namespace Ulams\CourseBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A citable piece of a Source Document. The id depends on the heading position, not the text
 * (a typo fix keeps it); `content_hash` changes with the text (Phase 3 change detection).
 *
 * @property string $id
 * @property string $source_id
 * @property int $ordinal
 * @property array $heading_path
 * @property string|null $section
 * @property int $level
 * @property string $text
 * @property int|null $page_start
 * @property int|null $page_end
 * @property int $token_estimate
 * @property string $content_hash
 */
class Fragment extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'course_builder_fragments';

    protected $guarded = [];

    protected $casts = [
        'heading_path' => 'array',
        'ordinal' => 'integer',
        'level' => 'integer',
        'char_start' => 'integer',
        'char_end' => 'integer',
        'page_start' => 'integer',
        'page_end' => 'integer',
        'token_estimate' => 'integer',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    /** "§2.3 Brewing ratios" */
    public function label(): string
    {
        $path = (array) $this->heading_path;
        $title = $path === [] ? 'Introduction' : (string) end($path);

        return trim(($this->section ? '§' . $this->section . ' ' : '') . $title);
    }
}
