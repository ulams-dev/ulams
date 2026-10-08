<?php

namespace EscolaLms\TopicTypes\Models\TopicContent;

use EscolaLms\TopicTypes\Database\Factories\TopicContent\Cmi5AuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @OA\Schema(
 *      schema="TopicCmi5Au",
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
 *      )
 * )
 */
class Cmi5Au extends AbstractTopicContent
{
    use HasFactory;

    public $table = 'topic_cmi5_aus';

    public $fillable = [
        'value',
    ];

    protected $casts = [
        'value' => 'integer',
    ];

    public static function rules(): array
    {
        return [
            'value' => ['required', 'integer', 'exists:cmi5_aus,id']
        ];
    }

    protected static function newFactory(): Cmi5AuFactory
    {
        return Cmi5AuFactory::new();
    }

    public function fixAssetPaths(): array
    {
        // TODO
        return [];
    }

    public function getMorphClass()
    {
        return self::class;
    }
}
