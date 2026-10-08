<?php

namespace EscolaLms\TopicTypes\Models\TopicContent;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

/**
 * @OA\Schema(
 *      schema="TopicAudio",
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
 * @property int $length
 */
class Audio extends AbstractTopicFileContent
{
    use HasFactory;

    public $table = 'topic_audios';

    protected $fillable = [
        'value',
        'length',
    ];

    protected $casts = [
        'id' => 'integer',
        'value' => 'string',
        'length' => 'integer',
    ];

    public static function rules(): array
    {
        return [
            'value' => ['required', 'mimes:mp3,ogg'],
            'length' => ['sometimes', 'integer'],
        ];
    }

    protected static function newFactory()
    {
        return \EscolaLms\TopicTypes\Database\Factories\TopicContent\AudioFactory::new();
    }

    protected function processUploadedFiles(): void
    {
        try {
            $media = FFMpeg::open($this->value);
            $this->length = $media->getDurationInMiliseconds();
        } catch (Exception $exception) {
            $this->length = 0;
            Log::error($exception->getMessage());
        }
    }

    public function processMetadataInfo(): void
    {
        $this->processUploadedFiles();
    }

    public function getStoragePathFinalSegment(): string
    {
        return 'audio';
    }

    public function getMorphClass()
    {
        return self::class;
    }
}
