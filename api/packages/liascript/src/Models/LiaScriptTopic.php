<?php

namespace Ulams\LiaScript\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
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
    /** Folder of the course text inside topic/<id>/ of a course export. */
    public const EXPORT_FOLDER = 'liascript';

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

    /**
     * Course export: writes the current version (README.md and its assets) to
     * course/<course>/topic/<topic>/liascript/, which the export copies into the archive. The
     * document itself and its versions stay where they are.
     */
    public function fixAssetPaths(): array
    {
        $topic = $this->topic;
        $course = $topic?->lesson?->course;
        $version = $this->document?->version($this->document->current_version);
        if ($course === null || $version === null) {
            return [];
        }

        $target = sprintf('course/%d/topic/%d/%s', $course->getKey(), $topic->getKey(), self::EXPORT_FOLDER);
        $source = Storage::disk(config('ulams_liascript.disk') ?: config('filesystems.default'));
        Storage::deleteDirectory($target);
        Storage::put($target . '/README.md', $version->markdown);
        foreach ($version->assets ?? [] as $relative => $asset) {
            $from = (string) ($asset['path'] ?? '');
            if (!is_string($relative) || str_contains($relative, '..') || $from === '' || !$source->exists($from)) {
                continue;
            }
            Storage::put($target . '/' . ltrim($relative, '/'), (string) $source->get($from));
        }

        return [];
    }

    public function getMorphClass()
    {
        return self::class;
    }
}
