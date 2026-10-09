<?php

namespace Ulams\LivingCourse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fragment as it was in one revision. Same shape as `course_builder_fragments` plus the
 * normalised hash used by change detection. The composite key is (revision_id, fragment_id).
 *
 * @property string $revision_id
 * @property string $fragment_id
 * @property string|null $file_path
 * @property int $ordinal
 * @property array $heading_path
 * @property string|null $section
 * @property int $level
 * @property string $text
 * @property int $token_estimate
 * @property string $content_hash
 * @property string $normalised_hash
 */
class RevisionFragment extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'living_course_revision_fragments';

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

    public function revision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'revision_id');
    }

    /** "rebase.md §2.3 Rebasing": same rule as the live fragment label. */
    public function label(): string
    {
        $path = (array) $this->heading_path;
        $title = $path === [] ? 'Introduction' : (string) end($path);
        $label = trim(($this->section ? '§' . $this->section . ' ' : '') . $title);

        return $this->file_path ? basename($this->file_path) . ' ' . $label : $label;
    }
}
