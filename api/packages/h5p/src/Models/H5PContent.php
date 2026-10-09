<?php

namespace Ulams\H5P\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ulams\H5P\Exceptions\H5PContentReadOnlyException;

/**
 * Read-only view of the H5P service's content table (`h5p.contents`, PostgreSQL).
 *
 * The H5P service (api/h5p) owns the `h5p` schema. Laravel only reads it; every
 * write (create, upload, update, delete) goes through
 * {@see \Ulams\H5P\Services\Contracts\H5PServiceClientContract}.
 *
 * @OA\Schema(
 *      schema="H5PContentSummary",
 *      @OA\Property(property="id", type="integer", example=12),
 *      @OA\Property(property="title", type="string", example="Capitals of Europe"),
 *      @OA\Property(property="library", type="string", example="H5P.MultiChoice 1.16"),
 *      @OA\Property(property="main_library", type="string", example="H5P.MultiChoice"),
 * )
 *
 * @property int $id
 * @property string|null $user_id
 * @property string $title
 * @property string $main_library    machine name, e.g. "H5P.MultiChoice"
 * @property string $library_version "major.minor", e.g. "1.16"
 * @property array $metadata         h5p.json
 * @property array $parameters       content.json
 * @property-read string $library    "H5P.MultiChoice 1.16"
 * @property-read int|null $count_h5p only with {@see scopeWithTopicCount()}
 */
class H5PContent extends Model
{
    public const TOPIC_TABLE = 'topic_h5ps';

    protected $table = 'h5p.contents';

    protected $guarded = ['*'];

    protected $casts = [
        'id' => 'integer',
        'metadata' => 'array',
        'parameters' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    private static ?bool $topicTableExists = null;

    protected static function booted(): void
    {
        $readOnly = static function (): void {
            throw new H5PContentReadOnlyException();
        };
        static::saving($readOnly);
        static::deleting($readOnly);
    }

    public function getLibraryAttribute(): string
    {
        return trim($this->main_library . ' ' . $this->library_version);
    }

    /**
     * The `content` object of an H5P topic (admin, client and export resources).
     */
    public function toTopicContent(): array
    {
        return [
            'id' => $this->getKey(),
            'title' => $this->title,
            'library' => $this->library,
            'main_library' => $this->main_library,
        ];
    }

    /**
     * Adds `count_h5p`: how many H5P topics (topic_h5ps.value) use the content.
     */
    public function scopeWithTopicCount(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->qualifyColumn('*'));
        }
        if (!self::topicTableExists()) {
            return $query->selectRaw('0 as count_h5p');
        }

        return $query->selectSub(
            fn (QueryBuilder $sub) => $sub->from(self::TOPIC_TABLE)
                ->selectRaw('count(*)')
                ->whereColumn(self::TOPIC_TABLE . '.value', $this->qualifyColumn('id')),
            'count_h5p'
        );
    }

    /**
     * Contents that no H5P topic references.
     */
    public function scopeUnused(Builder $query): Builder
    {
        if (!self::topicTableExists()) {
            return $query;
        }

        return $query->whereNotExists(
            fn (QueryBuilder $sub) => $sub->select(DB::raw(1))
                ->from(self::TOPIC_TABLE)
                ->whereColumn(self::TOPIC_TABLE . '.value', $this->qualifyColumn('id'))
        );
    }

    public function scopeOwnedBy(Builder $query, $userId): Builder
    {
        return $query->where($this->qualifyColumn('user_id'), (string) $userId);
    }

    private static function topicTableExists(): bool
    {
        return self::$topicTableExists ??= Schema::hasTable(self::TOPIC_TABLE);
    }
}
