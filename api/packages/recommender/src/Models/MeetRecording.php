<?php

namespace EscolaLms\Recommender\Models;

use EscolaLms\Recommender\Database\Factories\MeetRecordingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @OA\Schema(
 *     schema="MeetRecording",
 *     required={"model_type", "model_id", "term"},
 *     @OA\Property(
 *           property="id",
 *           description="id",
 *           type="integer",
 *       ),
 *     @OA\Property(
 *           property="model_type",
 *           description="model_type",
 *           type="string",
 *       ),
 *     @OA\Property(
 *            property="model_id",
 *            description="model_id",
 *            type="integer",
 *        ),
 *     @OA\Property(
 *            property="term",
 *            description="term",
 *            type="timestamp",
 *        ),
 *     @OA\Property(
 *            property="start_at",
 *            description="start_at",
 *            type="datetime",
 *        ),
 *     @OA\Property(
 *             property="end_at",
 *             description="end_at",
 *             type="datetime",
 *         ),
 *     @OA\Property(
 *            property="url",
 *            description="url",
 *            type="string",
 *        ),
 *     @OA\Property(
 *            property="url_expires_at",
 *            description="url_expires_at",
 *            type="datetime",
 *        ),
 *     @OA\Property(
 *             property="processing_video",
 *             description="processing_video",
 *             type="bool",
 *         ),
 * )
 */
class MeetRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_type',
        'model_id',
        'term',
        'start_at',
        'end_at',
        'url',
        'url_expires_at',
        'processing_video',
    ];

    protected $casts = [
        'term' => 'datetime',
        'time' => 'datetime',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'url_expires_at' => 'datetime',
    ];

    public function getIsUrlValidAttribute(): bool
    {
        return $this->url_expires_at && $this->url_expires_at->isFuture();
    }

    public function termAnalytic(): HasOne
    {
        return $this->hasOne(TermAnalytic::class);
    }

    public function MeetRecordingScreens(): HasMany
    {
        return $this->hasMany(MeetRecordingScreen::class);
    }

    protected static function newFactory(): MeetRecordingFactory
    {
        return MeetRecordingFactory::new();
    }
}
