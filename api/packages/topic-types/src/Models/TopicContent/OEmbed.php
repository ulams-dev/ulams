<?php

namespace EscolaLms\TopicTypes\Models\TopicContent;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @OA\Schema(
 *      schema="TopicOEmbed",
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
class OEmbed extends AbstractTopicContent
{
    use HasFactory;

    public $table = 'topic_oembeds';

    /**
     * Validation rules.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'value' => ['required', 'string'],
        ];
    }

    protected static function newFactory()
    {
        return \EscolaLms\TopicTypes\Database\Factories\TopicContent\OEmbedFactory::new();
    }

    public function fixAssetPaths(): array
    {
        return [];
    }

    public function getMorphClass()
    {
        return self::class;
    }
}
