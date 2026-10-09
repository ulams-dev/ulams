<?php

namespace Ulams\LivingCourse\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;

/**
 * How one source of a builder session is kept in sync: the connector, its configuration and
 * secrets, the schedule, and the two revision pointers (ADR 0030): `synced_revision_id` is what
 * the course reflects, `latest_revision_id` the newest fetched state.
 *
 * @property string $id
 * @property string $session_id
 * @property string $source_id
 * @property string $connector
 * @property array|null $config
 * @property array|null $secrets
 * @property string $webhook_id
 * @property string $schedule manual | hourly | daily | weekly
 * @property bool $auto_analyse
 * @property array|null $settings
 * @property string $status active | paused | error
 * @property string|null $synced_revision_id
 * @property string|null $latest_revision_id
 * @property int $failure_count
 */
class Connection extends Model
{
    use HasUlids;

    public const SCHEDULES = ['manual', 'hourly', 'daily', 'weekly'];

    protected $table = 'living_course_connections';

    protected $guarded = [];

    /** Secrets are write-only: never part of a serialised model. */
    protected $hidden = ['secrets'];

    protected $casts = [
        'config' => 'array',
        'secrets' => 'encrypted:array',
        'settings' => 'array',
        'auto_analyse' => 'boolean',
        'failure_count' => 'integer',
        'last_checked_at' => 'datetime',
        'next_check_at' => 'datetime',
        'last_change_at' => 'datetime',
    ];

    public static function newWebhookId(): string
    {
        return strtolower(Str::random(26));
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(Revision::class, 'connection_id')->orderBy('number');
    }

    public function syncedRevision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'synced_revision_id');
    }

    public function latestRevision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'latest_revision_id');
    }

    /** @return array<string,mixed> */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }
}
