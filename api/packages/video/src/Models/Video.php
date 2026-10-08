<?php

namespace EscolaLms\Video\Models;

use EscolaLms\TopicTypes\Models\TopicContent\Video as TopicContentVideo;
use EscolaLms\Video\Database\Factories\VideoFactory;

/**
 * @OA\Schema(
 *      schema="TopicVideo",
 *      required={"value"},
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          @OA\Schema(
 *             type="integer",
 *         )
 *      ),
 *      @OA\Property(
 *          property="value",
 *          description="value",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="poster",
 *          description="poster",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="hls",
 *          description="hls",
 *          type="string"
 *      )
 * )
 */
class Video extends TopicContentVideo
{
    public $fillable = [
        'value',
        'poster',
        'width',
        'height',
    ];

    protected $casts = [
        'id' => 'integer',
        'value' => 'string',
        'poster' => 'string',
        'width' => 'integer',
        'height' => 'integer',
    ];

    protected $appends = [
        'url',
        'poster_url',
    ];

    protected static function newFactory()
    {
        return VideoFactory::new();
    }
}
