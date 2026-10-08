<?php

namespace EscolaLms\TopicTypes\Models\TopicContent;

use EscolaLms\Scorm\Services\Contracts\ScormServiceContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Peopleaps\Scorm\Model\ScormScoModel;

/**
 * @OA\Schema(
 *      schema="TopicScormSco",
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
 *      @OA\Property(
 *          property="uuid",
 *          description="uuid",
 *          @OA\Schema(
 *             type="string",
 *         )
 *      ),
 * )
 */
class ScormSco extends AbstractTopicContent
{
    use HasFactory;

    public $table = 'topic_scorm_scos';

    public $fillable = [
        'value',
    ];

    protected $casts = [
        'value' => 'integer',
    ];

    public static function rules(): array
    {
        return [
            'value' => ['required', 'integer', 'exists:scorm_sco,id']
        ];
    }

    protected static function newFactory()
    {
        return \EscolaLms\TopicTypes\Database\Factories\TopicContent\ScormScoFactory::new();
    }

    public function fixAssetPaths(): array
    {
        $scormSco = ScormScoModel::find($this->value);
        if (!$scormSco) {
            return [];
        }

        $topic = $this->topic;
        $course = $topic->lesson->course;

        $destination = sprintf('course/%d/topic/%d/%s', $course->id, $topic->id, 'export.zip');

        /** @var ScormServiceContract $service */
        $service = app(ScormServiceContract::class);
        // @phpstan-ignore-next-line
        $zipPath = $service->zipScorm(ScormScoModel::find($this->value)->scorm->getKey());

        if (Storage::exists($destination)) {
            Storage::delete($destination);
        }

        $inputStream = Storage::disk('local')->getDriver()->readStream($zipPath);
        Storage::getDriver()->writeStream($destination, $inputStream);

        return [[$zipPath, $destination]];
    }

    public function getMorphClass()
    {
        return self::class;
    }
}
