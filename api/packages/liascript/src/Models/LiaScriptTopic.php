<?php

namespace Ulams\LiaScript\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ulams\TopicTypes\Models\TopicContent\AbstractTopicContent;

/**
 * @OA\Schema(
 *      schema="TopicLiaScript",
 *      required={"value"},
 *      @OA\Property(property="id", type="integer"),
 *      @OA\Property(property="value", type="integer", description="LiaScript document id (its current version is played)")
 * )
 *
 * Topic type: plays the current version of a LiaScript document on the tenant content origin.
 *
 * @property int $id
 * @property int $value
 * @property-read LiaScriptDocument|null $document
 */
class LiaScriptTopic extends AbstractTopicContent
{
    public $table = 'topic_liascripts';

    protected $fillable = ['value'];

    protected $casts = ['value' => 'integer'];

    public static function rules(): array
    {
        return [
            'value' => ['required', 'integer', 'exists:liascript_documents,id'],
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(LiaScriptDocument::class, 'value');
    }

    /** Assets live with the document, not in the course folder. */
    public function fixAssetPaths(): array
    {
        return [];
    }

    public function getMorphClass()
    {
        return self::class;
    }
}
