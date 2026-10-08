<?php

namespace EscolaLms\Recommender\Services;

use EscolaLms\Recommender\Dto\MeetRecordingDto;
use EscolaLms\Recommender\Dto\MeetRecordingScreenDto;
use EscolaLms\Recommender\Enum\EmotionsEnum;
use EscolaLms\Recommender\Enum\MeetRecordingEnum;
use EscolaLms\Recommender\EscolaLmsRecommenderServiceProvider;
use EscolaLms\Recommender\Events\AggregatedFrameStored;
use EscolaLms\Recommender\Exceptions\MeetRecordingActiveException;
use EscolaLms\Recommender\Exceptions\RecommenderDisabledException;
use EscolaLms\Recommender\Jobs\PredictSatisfactionJob;
use EscolaLms\Recommender\Jobs\ProcessingMeetingFramesJob;
use EscolaLms\Recommender\Jobs\UpdateTermAnalyticJob;
use EscolaLms\Recommender\Models\AggregatedFrame;
use EscolaLms\Recommender\Models\MeetRecording;
use EscolaLms\Recommender\Models\MeetRecordingScreen;
use EscolaLms\Recommender\Models\TermAnalytic;
use EscolaLms\Recommender\Models\Topic;
use EscolaLms\Recommender\Repositories\Contracts\TopicRepositoryContract;
use EscolaLms\Recommender\Services\Contracts\RecommenderServiceContract;
use EscolaLms\Recommender\Dto\AggregatedFrameDto;
use EscolaLms\TopicTypes\Models\TopicContent\H5P;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class RecommenderService implements RecommenderServiceContract
{
    private TopicRepositoryContract $topicRepository;

    private $class_type_page = [
        'PDF_page',
    ];

    private $class_type_length = [
        'H5P.Summary_length',
        'H5P.ImageHotspotQuestion_length',
        'RichText_length',
        'H5P.DragText_length',
        'H5P.Blanks_length',
        'H5P.TrueFalse_length',
        'H5P.QuestionSet_length',
        'H5P.ImageJuxtaposition_length',
        'H5P.Accordion_length',
        'H5P.DragQuestion_length',
        'H5P.SingleChoiceSet_length',
        'H5P.MultiChoice_length',
        'H5P.GuessTheAnswer_length',
        'H5P.Agamotto_length',
        'H5P.Collage_length',
        'H5P.SortParagraphs_length',
        'H5P.InteractiveVideo_length',
        'H5P.ImageHotspots_length',
        'H5P.Dialogcards_length',
        'H5P.Essay_length',
        'PDF_length',
        'Video_length',
        'H5P.ImageSlider_length',
    ];

    private $class_type = [
        'OEmbed',
        'H5P.Summary',
        'H5P.FindTheWords',
        'H5P.ImageHotspotQuestion',
        'RichText',
        'H5P.DragText',
        'H5P.Blanks',
        'H5P.TrueFalse',
        'H5P.QuestionSet',
        'H5P.Crossword',
        'H5P.ImageJuxtaposition',
        'H5P.Accordion',
        'H5P.CoursePresentation',
        'H5P.MultiMediaChoice',
        'H5P.DragQuestion',
        'H5P.SingleChoiceSet',
        'H5P.MultiChoice',
        'H5P.Column',
        'Image',
        'H5P.GuessTheAnswer',
        'H5P.MemoryGame',
        'H5P.Flashcards',
        'H5P.Chart',
        'H5P.Cornell',
        'H5P.InteractiveBook',
        'H5P.Dialogcards',
        'H5P.Agamotto',
        'H5P.Collage',
        'H5P.ImageSequencing',
        'H5P.SortParagraphs',
        'H5P.InteractiveVideo',
        'H5P.Audio',
        'H5P.ImageHotspots',
        'H5P.Timeline',
        'H5P.BranchingScenario',
        'H5P.InfoWall',
        'H5P.Essay',
        'PDF',
        'Video',
        'H5P.MarkTheWords',
        'H5P.PersonalityQuiz',
        'H5P.ImageSlider',
        'H5P.KewArCode',
        'H5P.ImagePair',
    ];

    public function __construct(
        TopicRepositoryContract $topicRepository
    )
    {
        $this->topicRepository = $topicRepository;
    }

    public function completionOfCourse(int $courseId): array
    {
        if (!config(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.enabled')) {
            throw new RecommenderDisabledException('Recommender is disabled.');
        }

        return $this->getResult('course', $this->makeCourseData($courseId));
    }

    public function matchTopicType(int $lessonId): array
    {
        if (!config(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.enabled')) {
            throw new RecommenderDisabledException('Recommender is disabled.');
        }

        return $this->getResult('exercise/match', $this->makeTopicData($lessonId));
    }

    public function makeCourseData(int $courseId): array
    {
        if (!config(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.course_model')) {
            throw new RecommenderDisabledException('Recommender course model is not set!');
        }

        $topics = $this->topicRepository->getAllByCourseId($courseId);

        $keys = $topics
            ->map(fn($topic) => [
                $this->getDatasetKey($topic),
                $this->getDatasetKey($topic, $this->class_type_length, '_length'),
                $this->getDatasetKey($topic, $this->class_type_page, '_page'),
            ])
            ->flatten()
            ->unique()
            ->filter()
            ->values()
            ->toArray();


        $dataset = array_fill_keys($keys, 0);

        foreach ($topics as $topic) {
            $key = $this->getDatasetKey($topic);

            if (empty($key)) {
                continue;
            }
            if (key_exists($key, $dataset)) {
                $dataset[$key]++;
            }
            if (key_exists($key . '_length', $dataset)) {
                $dataset[$key . '_length'] += $topic->topicable->length;
            }
            if (key_exists($key . '_page', $dataset)) {
                $dataset[$key . '_page'] += $topic->topicable->page;
            }
        }

        return $dataset;
    }

    public function makeTopicData(int $lessonId): array
    {
        if (!config(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.exercise_model')) {
            throw new RecommenderDisabledException('Recommender exercise model is not set!');
        }

        $limit = 5;
        $marker = 1.0;
        $topics = $this->topicRepository->getAllByLessonId($lessonId);
        $courseId = $topics->first()->lesson->course_id;
        $topicsCount = $this->topicRepository->countTopicByCourseId($courseId);
        $topicsLimit = $topics->take($limit);
        $step = 1;

        $dataset = [
            'question_number' => ++$topicsCount
        ];

        /* @var $topicsLimit Topic[] */
        foreach ($topicsLimit as $topic) {
            $key = $this->getDatasetKey($topic);

            if (empty($key)) {
                $step++;
                continue;
            }

            $dataset[$key . '_' . $step] = $marker;
            $step++;
        }

        return $dataset;
    }

    private function getDatasetKey(Topic $topic, ?array $filterBy = [], ?string $suffix = null): ?string
    {
        if (!$filterBy) {
            $filterBy = $this->class_type;
        }

        $key = class_basename($topic->topicable) . $suffix;

        if ($topic->topicable_type === H5P::class) {
            $key = $topic->topicable->libraryName . $suffix;
        }

        return in_array($key, $filterBy) ? $key : null;
    }

    /**
     * @throws RecommenderDisabledException
     */
    private function getResult(string $url, array $data): array
    {
        if (!config(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.api_url')) {
            throw new RecommenderDisabledException('Recommender API URL is not set!');
        }

        $body = empty($data) ? (object) [] : $data;

        return Http::withBody(json_encode($body), 'application/json')
            ->post(config(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.api_url') . $url)
            ->throw()
            ->collect()
            ->get('data');
    }

    public function aggregatedFrameSave(AggregatedFrameDto $dto): void
    {
        $emotions = collect([
            EmotionsEnum::ANGRY => $dto->getAvgEmotionsAngry(),
            EmotionsEnum::DISGUSTED => $dto->getAvgEmotionsDisgusted(),
            EmotionsEnum::FEARFUL => $dto->getAvgEmotionsFearful(),
            EmotionsEnum::HAPPY => $dto->getAvgEmotionsHappy(),
            EmotionsEnum::NEUTRAL => $dto->getAvgEmotionsNeutral(),
            EmotionsEnum::SAD => $dto->getAvgEmotionsSad(),
            EmotionsEnum::SURPRISED => $dto->getAvgEmotionsSurprised(),
        ]);

        $maxEmotion = $emotions->sortDesc()->keys()->first();
        $maxEmotionValue = $emotions->get($maxEmotion);

        $aggregatedFrame = AggregatedFrame::query()->updateOrCreate(['external_id' => $dto->getExternalId()], array_merge($dto->toArray(), ['max_emotion' => $maxEmotion, 'max_emotion_value' => $maxEmotionValue]));

        event(new AggregatedFrameStored($aggregatedFrame));
        UpdateTermAnalyticJob::dispatch($aggregatedFrame);
    }

    public function aggregatedFrames(string $modelType, int $modelId, int $term, int $interval)
    {
        $sumColumns = [
            'sum_attention',
            'sum_emotions_angry',
            'sum_emotions_disgusted',
            'sum_emotions_fearful',
            'sum_emotions_happy',
            'sum_emotions_neutral',
            'sum_emotions_sad',
            'sum_emotions_surprised',
        ];

        $selectParts = [];

        $pgsql = DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql';
        if ($pgsql) {
            $selectParts[] = "TO_TIMESTAMP(FLOOR(EXTRACT(EPOCH FROM window_start) / {$interval}) * {$interval}) AT TIME ZONE 'UTC' as bucket_start";
        } else {
            $selectParts[] = "FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(window_start)/{$interval})*{$interval}) as bucket_start";
        }

        $selectParts[] = "MAX(window_end) as bucket_end";
        $selectParts[] = "SUM(count) as total_count";

        foreach ($sumColumns as $sumColumn) {
            $avgName = Str::replaceFirst('sum_', 'avg_', $sumColumn);

            $selectParts[] = "SUM($sumColumn) as $sumColumn";
            if ($pgsql) {
                $selectParts[] = "SUM($sumColumn) / NULLIF(SUM(count)::numeric, 0) as $avgName";
            } else {
                $selectParts[] = "SUM($sumColumn) / NULLIF(SUM(count), 0) AS $avgName";
            }
        }

        $selectRaw = implode(',', $selectParts);

        $term = Carbon::createFromTimestamp($term);

        return AggregatedFrame::query()
            ->selectRaw($selectRaw)
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->where('term', $term)
            ->groupBy('bucket_start')
            ->orderBy('bucket_start')
            ->get();
    }

    public function meetRecording(MeetRecordingDto $dto): MeetRecording
    {
        Log::info('meetRecording service', ['dto' => $dto->toArray()]);
        /** @var MeetRecording|null $meetRecording */
        $meetRecording = MeetRecording::query()
            ->where('model_type', $dto->getModelType())
            ->where('model_id', $dto->getModelId())
            ->where('term', $dto->getTerm())
            ->whereNull('end_at')
            ->latest('start_at')
            ->first();

        Log::info('found meetRecording: ' . $meetRecording ? 'true' : 'false');

        if ($dto->getAction() === MeetRecordingEnum::START_RECORDING) {
            Log::info('Action Start recording');

            if ($meetRecording) {
                throw new MeetRecordingActiveException('Active recording found for this term with ID: ' . $meetRecording->getKey(), 422);
            }

            /** @var MeetRecording $meet */
            $meet =  MeetRecording::query()->create($dto->toArray());

            TermAnalytic::query()->create([
                'model_type' => $dto->getModelType(),
                'model_id' => $dto->getModelId(),
                'term' => $dto->getTerm(),
                'meet_recording_id' => $meet->getKey(),
            ]);

            return $meet;
        }
        Log::info('Action end recording');

        if ($meetRecording === null) {
            Log::info('MeetRecording not found');
            throw new ModelNotFoundException();
        }

        $data = [];
        if ($dto->getUrlExpirationTimeMillis() !== null) {
            $data['url_expires_at'] = Carbon::now()->addMilliseconds($dto->getUrlExpirationTimeMillis());
            $data['processing_video'] = true;
        }
        Log::info('MeetRecording additional data: ' . json_encode($data));
        $meetRecording->update(array_merge($dto->toArray(), $data));
        Log::info('Updated meetRecording', ['meetRecording' => $meetRecording]);

        if (!$meetRecording->termAnalytic()->exists()) {
            Log::info('No term term Analytic in MeetRecording');
            TermAnalytic::query()->create([
                'model_type' => $dto->getModelType(),
                'model_id' => $dto->getModelId(),
                'term' => $dto->getTerm(),
                'meet_recording_id' => $meetRecording->getKey(),
            ]);
            $meetRecording->refresh();
        }

        Log::info('Dispatching PredictSatisfactionJob');
        PredictSatisfactionJob::dispatch($meetRecording->termAnalytic);
        Log::info('Dispatching ProcessingMeetingFramesJob');
        ProcessingMeetingFramesJob::dispatch($meetRecording)->delay(Carbon::now()->addSeconds(30));

        Log::info('Returning meetRecording', ['meetRecording' => $meetRecording]);
        return $meetRecording;
    }

    public function meetRecordingScreen(MeetRecordingScreenDto $dto): void
    {
        $term = Carbon::make($dto->getTerm());
        $folder = "{$dto->getModelType()}/{$dto->getModelId()}/{$term->getTimestamp()}/presentation";

        $recording = MeetRecording::query()
            ->where('model_type', $dto->getModelType())
            ->where('model_id', $dto->getModelId())
            ->where('term', $term)
            ->whereNull('end_at')
            ->latest('start_at')
            ->first();

        if (!$recording) {
            throw new \RuntimeException('No active recording found');
        }

        foreach ($dto->getFiles() as $file) {
            $screen = $file['file'];
            $extension = $screen instanceof UploadedFile ? $screen->getClientOriginalExtension() : Str::between($screen, 'data:image/', ';base64');
            $filePath = Storage::putFileAs($folder, $screen, Carbon::make($file['timestamp'])->getTimestamp() . '.' . $extension);

            MeetRecordingScreen::query()->create([
                'model_type' => $dto->getModelType(),
                'model_id' => $dto->getModelId(),
                'term' => $term,
                'file_path' => $filePath,
                'file_timestamp' => Carbon::parse($file['timestamp'])->utc(),
                'meet_recording_id' => $recording->getKey(),
            ]);
        };
    }
}
