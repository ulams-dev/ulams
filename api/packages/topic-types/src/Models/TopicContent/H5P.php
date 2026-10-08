<?php

namespace Ulams\TopicTypes\Models\TopicContent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use Ulams\H5P\Models\H5PContent;
use Ulams\H5P\Services\Contracts\H5PServiceClientContract;
use Ulams\TopicTypes\Database\Factories\TopicContent\H5PFactory;

/**
 * @OA\Schema(
 *      schema="TopicH5P",
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
 *          description="H5P content id (h5p.contents.id, H5P service)",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="content",
 *          ref="#/components/schemas/H5PContentSummary"
 *      )
 * )
 *
 * @property-read H5PContent|null $h5pContent
 */
class H5P extends AbstractTopicContent
{
    use HasFactory;

    public $table = 'topic_h5ps';

    protected $hidden = ['h5pContent'];

    public static function rules(): array
    {
        return [
            // H5PContent's table is h5p.contents; naming the model keeps the
            // validator from reading "h5p" as a connection name.
            'value' => ['required', 'integer', 'exists:' . H5PContent::class . ',id'],
        ];
    }

    protected static function newFactory()
    {
        return H5PFactory::new();
    }

    public function h5pContent(): BelongsTo
    {
        return $this->belongsTo(H5PContent::class, 'value');
    }

    /**
     * Exports the content as course/{course}/topic/{topic}/export.h5p (course export).
     */
    public function fixAssetPaths(): array
    {
        $topic = $this->topic;
        $course = $topic->lesson->course;
        $destination = sprintf('course/%d/topic/%d/%s', $course->id, $topic->id, 'export.h5p');
        $filepath = App::make(H5PServiceClientContract::class)->download((int) $this->value);

        try {
            if (Storage::exists($destination)) {
                Storage::delete($destination);
            }
            $inputStream = fopen($filepath, 'r');
            Storage::getDriver()->writeStream($destination, $inputStream);
            if (is_resource($inputStream)) {
                fclose($inputStream);
            }
        } finally {
            @unlink($filepath);
        }

        return [[$filepath, Storage::path($destination)]];
    }

    public function getLengthAttribute(): ?int
    {
        try {
            $content = $this->h5pContent;
            if (!$content) {
                return null;
            }
            $lengthKey = self::lengthConfig($content);
            if ($lengthKey === null) {
                return null;
            }
            if (!isset($lengthKey['length_key'])) {
                return $lengthKey['default_length'] ?? null;
            }
            // config keys are relative to {"params": content.json}
            $items = Arr::get(['params' => $content->parameters ?? []], $lengthKey['length_key']);

            return is_countable($items) ? count($items) : ($lengthKey['default_length'] ?? null);
        } catch (Throwable $e) {
            return null;
        }
    }

    public function getLibraryNameAttribute(): ?string
    {
        return $this->h5pContent->main_library ?? null;
    }

    public function getMorphClass()
    {
        return self::class;
    }

    /**
     * topic-h5p config entry for the content's library: exact "Machine.Name x.y" first,
     * then any configured version of the same machine name.
     */
    private static function lengthConfig(H5PContent $content): ?array
    {
        $keys = Config::get('topic-h5p', []);
        if (isset($keys[$content->library])) {
            return $keys[$content->library];
        }
        foreach ($keys as $uberName => $config) {
            if (Str::before($uberName, ' ') === $content->main_library) {
                return $config;
            }
        }

        return null;
    }
}
