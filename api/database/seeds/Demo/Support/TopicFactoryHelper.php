<?php

namespace Database\Seeders\Demo\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Ulams\Courses\Http\Requests\CreateTopicAPIRequest;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Repositories\Contracts\TopicRepositoryContract;
use Ulams\Courses\Repositories\Contracts\TopicResourceRepositoryContract;
use Ulams\TopicTypeGift\Dtos\GiftQuestionDto;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Services\Contracts\GiftQuestionServiceContract;
use Ulams\Interactive\Models\InteractiveTopic;
use Ulams\TopicTypeLayout\Models\LayoutTopic;
use Ulams\TopicTypeProject\Models\Project;
use Ulams\TopicTypes\Models\TopicContent\Audio;
use Ulams\TopicTypes\Models\TopicContent\Cmi5Au;
use Ulams\TopicTypes\Models\TopicContent\H5P;
use Ulams\TopicTypes\Models\TopicContent\Image;
use Ulams\TopicTypes\Models\TopicContent\OEmbed;
use Ulams\TopicTypes\Models\TopicContent\PDF;
use Ulams\TopicTypes\Models\TopicContent\RichText;
use Ulams\TopicTypes\Models\TopicContent\ScormSco;
use Ulams\TopicTypes\Models\TopicContent\Video;
use Ulams\LiaScript\Models\LiaScriptTopic;

/**
 * Creates topics the way the admin API does: a CreateTopicAPIRequest with the
 * topic fields, the content fields and uploaded files, passed to
 * TopicRepositoryContract::createFromRequest (same pattern as the course
 * import in courses-import-export ExportImportService).
 */
class TopicFactoryHelper
{
    public const TYPES = [
        'richtext' => RichText::class,
        'video' => Video::class,
        'audio' => Audio::class,
        'image' => Image::class,
        'pdf' => PDF::class,
        'oembed' => OEmbed::class,
        'h5p' => H5P::class,
        'scorm' => ScormSco::class,
        'cmi5' => Cmi5Au::class,
        'gift' => GiftQuiz::class,
        'project' => Project::class,
        'liascript' => LiaScriptTopic::class,
        'interactive' => InteractiveTopic::class,
        'layout' => LayoutTopic::class,
    ];

    private TopicRepositoryContract $topics;
    private TopicResourceRepositoryContract $resources;
    private GiftQuestionServiceContract $questions;

    public function __construct(
        TopicRepositoryContract $topics,
        TopicResourceRepositoryContract $resources,
        GiftQuestionServiceContract $questions
    ) {
        $this->topics = $topics;
        $this->resources = $resources;
        $this->questions = $questions;
    }

    /**
     * @param array<string, mixed>  $fields  topic fields (title, order, preview, can_skip, duration,
     *                                       summary, introduction, description, json) and content
     *                                       fields (value, length, max_attempts, ...)
     * @param array<string, string> $files   content file keys (value, poster) => local path
     */
    public function create(Lesson $lesson, string $type, array $fields, array $files = []): Topic
    {
        if (!isset(self::TYPES[$type])) {
            throw new RuntimeException("Unknown topic type $type");
        }
        $data = array_merge([
            'lesson_id' => $lesson->getKey(),
            'topicable_type' => self::TYPES[$type],
            'active' => true,
        ], $fields);
        if (isset($data['json']) && is_array($data['json'])) {
            $data['json'] = json_encode($data['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        // the topic rules accept strings only; drop empty optional texts
        foreach (['summary', 'introduction', 'description'] as $key) {
            if (array_key_exists($key, $data) && ($data[$key] === null || $data[$key] === '')) {
                unset($data[$key]);
            }
        }

        $request = new CreateTopicAPIRequest($data);
        foreach ($files as $key => $path) {
            $request->files->add([$key => new UploadedFile($path, basename($path), null, null, true)]);
        }
        $request->setValidator(Validator::make($request->all(), $request->rules()));

        return $this->topics->createFromRequest($request);
    }

    /**
     * Adds a downloadable resource to a topic.
     */
    public function addResource(Topic $topic, string $path, string $name): void
    {
        $resource = $this->resources->storeUploadedResourceForTopic($topic, new UploadedFile($path, $name, null, null, true));
        $this->resources->renameModel($resource, $name);
    }

    /**
     * Adds GIFT questions to a quiz topic and checks that each one parses to
     * the intended question type.
     *
     * @param array<int, array{type: string, gift: string, score?: int}> $questions
     * @return array<string, int> count per question type
     */
    public function addGiftQuestions(Topic $topic, array $questions): array
    {
        $quiz = $topic->topicable;
        if (!$quiz instanceof GiftQuiz) {
            throw new RuntimeException('Topic ' . $topic->getKey() . ' is not a GIFT quiz');
        }
        $counts = [];
        foreach (array_values($questions) as $index => $question) {
            $parsed = $this->questions->getType($question['gift']);
            if ($parsed !== $question['type']) {
                throw new RuntimeException(sprintf(
                    'GIFT question %d of "%s" parses as %s, expected %s',
                    $index + 1,
                    $topic->title,
                    $parsed,
                    $question['type']
                ));
            }
            $model = $this->questions->create(new GiftQuestionDto(
                $quiz->getKey(),
                $question['gift'],
                $question['score'] ?? 1,
                $index + 1,
                null
            ));
            $counts[$model->type] = ($counts[$model->type] ?? 0) + 1;
        }

        return $counts;
    }
}
