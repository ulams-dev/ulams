<?php

namespace Ulams\CourseBuilder\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A builder session: one course being built from sources by one author.
 *
 * @property string $id
 * @property int $author_id
 * @property string|null $title
 * @property string $status
 * @property array|null $brief
 * @property int $brief_version
 * @property array|null $state
 * @property string|null $current_version_id
 * @property string|null $applied_version_id
 * @property int|null $course_id
 * @property int|null $budget_tokens
 * @property int|null $budget_micro_usd
 */
class Session extends Model
{
    use HasUlids;
    use SoftDeletes;

    public const DRAFT = 'draft';
    public const INGESTING = 'ingesting';
    public const INTERVIEWING = 'interviewing';
    public const OUTLINING = 'outlining';
    public const OUTLINE_REVIEW = 'outline_review';
    public const GENERATING = 'generating';
    public const APPLY_REVIEW = 'apply_review';
    public const APPLYING = 'applying';
    public const APPLIED = 'applied';
    public const FAILED = 'failed';

    public const SUBJECT_TYPE = 'course_builder_session';

    protected $table = 'course_builder_sessions';

    protected $guarded = [];

    protected $casts = [
        'brief' => 'array',
        'state' => 'array',
        'brief_version' => 'integer',
        'course_id' => 'integer',
        'author_id' => 'integer',
        'tokens_used' => 'integer',
        'cost_micro_usd' => 'integer',
        'budget_tokens' => 'integer',
        'budget_micro_usd' => 'integer',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'author_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class, 'session_id')->orderBy('created_at');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Version::class, 'session_id')->orderBy('number');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class, 'session_id')->orderBy('created_at');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(Version::class, 'current_version_id');
    }

    /** @return array<string,mixed> */
    public function stateValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->state ?? [], $key, $default);
    }

    public function putState(string $key, mixed $value): void
    {
        $state = $this->state ?? [];
        data_set($state, $key, $value);
        $this->state = $state;
    }

    /** @return array{type:string,id:string} */
    public function subject(): array
    {
        return ['type' => self::SUBJECT_TYPE, 'id' => $this->id];
    }

    /** @return array{tokens:int,cost_micro_usd:int} */
    public function budget(): array
    {
        return [
            'tokens' => $this->budget_tokens ?: (int) config('course_builder.limits.session_tokens'),
            'cost_micro_usd' => $this->budget_micro_usd ?: (int) round(((float) config('course_builder.limits.session_cost_usd')) * 1000000),
        ];
    }

    /** @return int[] fragment ids are strings; returns all fragment ids of the session's sources */
    public function fragmentIds(): array
    {
        return Fragment::query()->whereIn('source_id', $this->sources()->pluck('id'))->pluck('id')->all();
    }
}
