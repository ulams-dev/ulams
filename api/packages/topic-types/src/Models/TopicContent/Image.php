<?php

namespace Ulams\TopicTypes\Models\TopicContent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Schema(
 *      schema="TopicImage",
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
 *
 * @property int $width
 * @property int $height
 */
class Image extends AbstractTopicFileContent
{
    use HasFactory;

    public $table = 'topic_images';

    protected $fillable = [
        'value',
        'width',
        'height',
    ];

    protected $casts = [
        'id' => 'integer',
        'value' => 'string',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public static function rules(): array
    {
        return [
            'value' => ['required', 'image:allow_svg'],
            'width' => ['sometimes', 'integer'],
            'height' => ['sometimes', 'integer'],
        ];
    }

    protected static function newFactory()
    {
        return \Ulams\TopicTypes\Database\Factories\TopicContent\ImageFactory::new();
    }

    public function getStoragePathFinalSegment(): string
    {
        return 'image';
    }

    protected function processUploadedFiles(): void
    {
        // Read the file through the disk (the internal S3 endpoint), never over its public URL: inside the
        // containers the public host (storage.localhost) resolves to the container itself.
        try {
            $contents = Storage::get($this->value);
        } catch (\Throwable) {
            return;
        }

        $sizes = is_string($contents) && $contents !== '' ? @getimagesizefromstring($contents) : false;
        if ($sizes) {
            $this->width = $sizes[0];
            $this->height = $sizes[1];
        }
    }

    public function processMetadataInfo(): void
    {
        $this->processUploadedFiles();
    }
}
