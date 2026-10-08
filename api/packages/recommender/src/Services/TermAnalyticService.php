<?php

namespace EscolaLms\Recommender\Services;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Recommender\Dto\PageDto;
use EscolaLms\Recommender\Dto\PredictSatisfactionDto;
use EscolaLms\Recommender\Dto\SatisfactionDto;
use EscolaLms\Recommender\Dto\TermAnalyticsFilterListDto;
use EscolaLms\Recommender\Enum\EmotionsEnum;
use EscolaLms\Recommender\Enum\SatisfactionStatusEnum;
use EscolaLms\Recommender\EscolaLmsRecommenderServiceProvider;
use EscolaLms\Recommender\Models\AggregatedFrame;
use EscolaLms\Recommender\Models\MeetRecording;
use EscolaLms\Recommender\Models\TermAnalytic;
use EscolaLms\Recommender\Repositories\Contracts\TermAnalyticsRepositoryContract;
use EscolaLms\Recommender\Services\Contracts\TermAnalyticServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TermAnalyticService implements TermAnalyticServiceContract
{
    public function __construct(
        private TermAnalyticsRepositoryContract $termAnalyticsRepository,
    ) {}

    public function updateTermAnalytic(string $modelType, int $modelId, Carbon $term, Carbon $startAt): void
    {
        DB::transaction(function () use ($modelType, $modelId, $term, $startAt) {
           $meet = MeetRecording::query()
               ->where('model_type', $modelType)
               ->where('model_id', $modelId)
               ->where('term', $term)
               ->where('start_at', '<=', $startAt)
               ->where(function ($query) use ($startAt) {
                   $query
                       ->whereNull('end_at')
                       ->orWhere('end_at', '>=', $startAt);
               })
               ->orderByDesc('start_at')
               ->firstOrFail();

           $query = AggregatedFrame::query()
               ->where('model_type', $modelType)
               ->where('model_id', $modelId)
               ->where('term', $term)
               ->where('window_start', '>=', $meet->start_at);

           if ($meet->end_at) {
               $query->where('window_start', '<=', $meet->end_at);
           }

            $data = $query
                ->selectRaw("
                SUM(count) as count,
                COUNT(id) as aggregated_frames_count,
                MAX(updated_at) as last_frame_at,

                SUM(sum_attention) as sum_attention,
                SUM(sum_emotions_angry) as sum_emotions_angry,
                SUM(sum_emotions_disgusted) as sum_emotions_disgusted,
                SUM(sum_emotions_fearful) as sum_emotions_fearful,
                SUM(sum_emotions_happy) as sum_emotions_happy,
                SUM(sum_emotions_neutral) as sum_emotions_neutral,
                SUM(sum_emotions_sad) as sum_emotions_sad,
                SUM(sum_emotions_surprised) as sum_emotions_surprised
            ")
                ->first();

            if (!$data || $data->count == 0) {
                return;
            }

            $emotions = collect([
                EmotionsEnum::ANGRY => $data->sum_emotions_angry,
                EmotionsEnum::DISGUSTED => $data->sum_emotions_disgusted,
                EmotionsEnum::FEARFUL => $data->sum_emotions_fearful,
                EmotionsEnum::HAPPY => $data->sum_emotions_happy,
                EmotionsEnum::NEUTRAL => $data->sum_emotions_neutral,
                EmotionsEnum::SAD => $data->sum_emotions_sad,
                EmotionsEnum::SURPRISED => $data->sum_emotions_surprised,
            ]);

            $maxEmotion = $emotions->sortDesc()->keys()->first();

            $termAnalytic = TermAnalytic::updateOrCreate(
                ['model_type' => $modelType, 'model_id' => $modelId, 'term' => $term, 'meet_recording_id' => $meet->getKey()],
                [
                    'count' => $data->count,
                    'aggregated_frames_count' => $data->aggregated_frames_count,
                    'sum_attention' => $data->sum_attention,

                    'sum_emotions_angry' => $data->sum_emotions_angry,
                    'sum_emotions_disgusted' => $data->sum_emotions_disgusted,
                    'sum_emotions_fearful' => $data->sum_emotions_fearful,
                    'sum_emotions_happy' => $data->sum_emotions_happy,
                    'sum_emotions_neutral' => $data->sum_emotions_neutral,
                    'sum_emotions_sad' => $data->sum_emotions_sad,
                    'sum_emotions_surprised' => $data->sum_emotions_surprised,
                    'avg_attention' => $data->sum_attention / $data->count,
                    'avg_emotions_angry' => $data->sum_emotions_angry / $data->count,
                    'avg_emotions_disgusted' => $data->sum_emotions_disgusted / $data->count,
                    'avg_emotions_fearful' => $data->sum_emotions_fearful / $data->count,
                    'avg_emotions_happy' => $data->sum_emotions_happy / $data->count,
                    'avg_emotions_neutral' => $data->sum_emotions_neutral / $data->count,
                    'avg_emotions_sad' => $data->sum_emotions_sad / $data->count,
                    'avg_emotions_surprised' => $data->sum_emotions_surprised / $data->count,

                    'max_emotion' => $maxEmotion,
                    'max_emotion_value' => $emotions->get($maxEmotion) / $data->count,
                    'last_frame_at' => $data->last_frame_at,
                ]
            );

            $query
                ->whereNull('term_analytic_id')
                ->update([
                    'term_analytic_id' => $termAnalytic->getKey(),
                ]);
        });
    }

    public function rebuildTermAnalytic(string $modelType, int $modelId, Carbon $term): void
    {
        DB::transaction(function () use ($modelType, $modelId, $term) {
            $query = AggregatedFrame::query()
                ->where('model_type', $modelType)
                ->where('model_id', $modelId)
                ->where('term', $term);

            $data = $query
                ->selectRaw("
                SUM(count) as count,
                COUNT(id) as aggregated_frames_count,
                MAX(updated_at) as last_frame_at,

                SUM(sum_attention) as sum_attention,
                SUM(sum_emotions_angry) as sum_emotions_angry,
                SUM(sum_emotions_disgusted) as sum_emotions_disgusted,
                SUM(sum_emotions_fearful) as sum_emotions_fearful,
                SUM(sum_emotions_happy) as sum_emotions_happy,
                SUM(sum_emotions_neutral) as sum_emotions_neutral,
                SUM(sum_emotions_sad) as sum_emotions_sad,
                SUM(sum_emotions_surprised) as sum_emotions_surprised
            ")
                ->first();

            if (!$data || $data->count == 0) {
                return;
            }

            $emotions = collect([
                EmotionsEnum::ANGRY => $data->sum_emotions_angry,
                EmotionsEnum::DISGUSTED => $data->sum_emotions_disgusted,
                EmotionsEnum::FEARFUL => $data->sum_emotions_fearful,
                EmotionsEnum::HAPPY => $data->sum_emotions_happy,
                EmotionsEnum::NEUTRAL => $data->sum_emotions_neutral,
                EmotionsEnum::SAD => $data->sum_emotions_sad,
                EmotionsEnum::SURPRISED => $data->sum_emotions_surprised,
            ]);

            $maxEmotion = $emotions->sortDesc()->keys()->first();

            $termAnalytic = TermAnalytic::updateOrCreate(
                ['model_type' => $modelType, 'model_id' => $modelId, 'term' => $term],
                [
                    'count' => $data->count,
                    'aggregated_frames_count' => $data->aggregated_frames_count,
                    'sum_attention' => $data->sum_attention,

                    'sum_emotions_angry' => $data->sum_emotions_angry,
                    'sum_emotions_disgusted' => $data->sum_emotions_disgusted,
                    'sum_emotions_fearful' => $data->sum_emotions_fearful,
                    'sum_emotions_happy' => $data->sum_emotions_happy,
                    'sum_emotions_neutral' => $data->sum_emotions_neutral,
                    'sum_emotions_sad' => $data->sum_emotions_sad,
                    'sum_emotions_surprised' => $data->sum_emotions_surprised,
                    'avg_attention' => $data->sum_attention / $data->count,
                    'avg_emotions_angry' => $data->sum_emotions_angry / $data->count,
                    'avg_emotions_disgusted' => $data->sum_emotions_disgusted / $data->count,
                    'avg_emotions_fearful' => $data->sum_emotions_fearful / $data->count,
                    'avg_emotions_happy' => $data->sum_emotions_happy / $data->count,
                    'avg_emotions_neutral' => $data->sum_emotions_neutral / $data->count,
                    'avg_emotions_sad' => $data->sum_emotions_sad / $data->count,
                    'avg_emotions_surprised' => $data->sum_emotions_surprised / $data->count,

                    'max_emotion' => $maxEmotion,
                    'max_emotion_value' => $emotions->get($maxEmotion) / $data->count,
                    'last_frame_at' => $data->last_frame_at,
                ]
            );

            AggregatedFrame::query()
                ->where('model_type', $modelType)
                ->where('model_id', $modelId)
                ->where('term', $term)
                ->whereNull('term_analytic_id')
                ->update([
                    'term_analytic_id' => $termAnalytic->getKey(),
                ]);
        });
    }

    public function termAnalyticsList(string $modelType, TermAnalyticsFilterListDto $criteriaDto, PageDto $pageDto, OrderDto $orderDto): LengthAwarePaginator
    {
        return $this->termAnalyticsRepository->findByCriteria($modelType, $criteriaDto, $pageDto->getPerPage(), $orderDto);
    }

    public function termAnalytic(string $modelType, int $id): TermAnalytic
    {
        return $this->termAnalyticsRepository->findById($modelType, $id);
    }

    public function modelAnalyticsForTerm(int $termAnalyticId): AggregatedFrame
    {
        $pgsql = DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql';

        if ($pgsql) {
            $selectRaw = "
                term,

                SUM(count) as total_count,

                SUM(sum_attention) / NULLIF(SUM(count)::numeric,0) as avg_attention,
                SUM(sum_emotions_angry) / NULLIF(SUM(count)::numeric,0) as avg_emotions_angry,
                SUM(sum_emotions_disgusted) / NULLIF(SUM(count)::numeric,0) as avg_emotions_disgusted,
                SUM(sum_emotions_fearful) / NULLIF(SUM(count)::numeric,0) as avg_emotions_fearful,
                SUM(sum_emotions_happy) / NULLIF(SUM(count)::numeric,0) as avg_emotions_happy,
                SUM(sum_emotions_neutral) / NULLIF(SUM(count)::numeric,0) as avg_emotions_neutral,
                SUM(sum_emotions_sad) / NULLIF(SUM(count)::numeric,0) as avg_emotions_sad,
                SUM(sum_emotions_surprised) / NULLIF(SUM(count)::numeric,0) as avg_emotions_surprised
            ";
        } else {
            $selectRaw = "
                term,

                SUM(count) as total_count,

                SUM(sum_attention) / NULLIF(SUM(count), 0) as avg_attention,
                SUM(sum_emotions_angry) / NULLIF(SUM(count), 0) as avg_emotions_angry,
                SUM(sum_emotions_disgusted) / NULLIF(SUM(count), 0) as avg_emotions_disgusted,
                SUM(sum_emotions_fearful) / NULLIF(SUM(count), 0) as avg_emotions_fearful,
                SUM(sum_emotions_happy) / NULLIF(SUM(count), 0) as avg_emotions_happy,
                SUM(sum_emotions_neutral) / NULLIF(SUM(count), 0) as avg_emotions_neutral,
                SUM(sum_emotions_sad) / NULLIF(SUM(count), 0) as avg_emotions_sad,
                SUM(sum_emotions_surprised) / NULLIF(SUM(count), 0) as avg_emotions_surprised
            ";
        }

        return AggregatedFrame::query()
            ->selectRaw($selectRaw)
            ->where('term_analytic_id', $termAnalyticId)
            ->groupBy('term')
            ->first();
    }

    public function modelAnalytics(string $modelType, int $modelId, ?int $term = null): Collection|AggregatedFrame
    {
        $pgsql = DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql';

        if ($pgsql) {
            $selectRaw = "
                term,

                SUM(count) as total_count,

                SUM(sum_attention) / NULLIF(SUM(count)::numeric,0) as avg_attention,
                SUM(sum_emotions_angry) / NULLIF(SUM(count)::numeric,0) as avg_emotions_angry,
                SUM(sum_emotions_disgusted) / NULLIF(SUM(count)::numeric,0) as avg_emotions_disgusted,
                SUM(sum_emotions_fearful) / NULLIF(SUM(count)::numeric,0) as avg_emotions_fearful,
                SUM(sum_emotions_happy) / NULLIF(SUM(count)::numeric,0) as avg_emotions_happy,
                SUM(sum_emotions_neutral) / NULLIF(SUM(count)::numeric,0) as avg_emotions_neutral,
                SUM(sum_emotions_sad) / NULLIF(SUM(count)::numeric,0) as avg_emotions_sad,
                SUM(sum_emotions_surprised) / NULLIF(SUM(count)::numeric,0) as avg_emotions_surprised
            ";
        } else {
            $selectRaw = "
                term,

                SUM(count) as total_count,

                SUM(sum_attention) / NULLIF(SUM(count), 0) as avg_attention,
                SUM(sum_emotions_angry) / NULLIF(SUM(count), 0) as avg_emotions_angry,
                SUM(sum_emotions_disgusted) / NULLIF(SUM(count), 0) as avg_emotions_disgusted,
                SUM(sum_emotions_fearful) / NULLIF(SUM(count), 0) as avg_emotions_fearful,
                SUM(sum_emotions_happy) / NULLIF(SUM(count), 0) as avg_emotions_happy,
                SUM(sum_emotions_neutral) / NULLIF(SUM(count), 0) as avg_emotions_neutral,
                SUM(sum_emotions_sad) / NULLIF(SUM(count), 0) as avg_emotions_sad,
                SUM(sum_emotions_surprised) / NULLIF(SUM(count), 0) as avg_emotions_surprised
            ";
        }

        $query = AggregatedFrame::query()
            ->selectRaw($selectRaw)
            ->where('model_type', $modelType)
            ->where('model_id', $modelId);

        if ($term) {
            return $query->where(
                'term',
                Carbon::createFromTimestamp($term)
            )
                ->groupBy('term')
                ->orderBy('term')
                ->first();
        }

        return $query
            ->groupBy('term')
            ->orderBy('term')
            ->get();
    }

    public function aggregatedFrames(int $termId, int $interval): Collection
    {
        $termAnalytic = TermAnalytic::query()->findOrFail($termId);
        $recordingId = $termAnalytic->meet_recording_id;

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

        $buckets = AggregatedFrame::query()
            ->selectRaw($selectRaw)
            ->where('term_analytic_id', $termId)
            ->groupBy('bucket_start');

        return DB::query()
            ->fromSub($buckets, 'b')
            ->select([
                'b.*',

                DB::raw("(
                SELECT MIN(s.file_timestamp)
                FROM meet_recording_screens s
                WHERE s.meet_recording_id = {$recordingId}
                AND s.file_timestamp >= b.bucket_start
                AND s.file_timestamp <= b.bucket_end
            ) as screen_timestamp"),

                DB::raw("(
                SELECT s.file_path
                FROM meet_recording_screens s
                WHERE s.meet_recording_id = {$recordingId}
                AND s.file_timestamp = (
                    SELECT MIN(s2.file_timestamp)
                    FROM meet_recording_screens s2
                    WHERE s2.meet_recording_id = {$recordingId}
                    AND s2.file_timestamp >= b.bucket_start
                    AND s2.file_timestamp <= b.bucket_end
                )
                LIMIT 1
            ) as screen_path"),
            ])
            ->orderBy('b.bucket_start')
            ->get();
    }

    public function saveSatisfaction(SatisfactionDto $dto): void
    {
        TermAnalytic::query()->where('id', $dto->getTermAnalyticId())->update([
            'mean_predicted_rating' => $dto->getMeanPredictedRating(),
            'satisfaction_models' => json_encode($dto->getSatisfactionModels()),
        ]);
    }

    /**
     * @throws RequestException
     */
    public function predictSatisfaction(TermAnalytic $termAnalytic): void
    {
        // The satisfaction setting is a list of {model, enabled} entries. It is a list (not a map)
        // so it round-trips through the administrable-config "array" type and the admin panel, the
        // same way video.bitrates does. Selected models are the enabled ones. The whole downstream
        // path (frames, Makaruk, storage) works on a plain list of model IDs, so multi-model support
        // is already in place; enabling more models is just flipping "enabled" in this setting.
        $satisfactionModelsConfig = config(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.satisfaction_models');
        $satisfactionModels = collect(is_array($satisfactionModelsConfig) ? $satisfactionModelsConfig : [])
            ->filter(fn ($entry) => is_array($entry) && ($entry['enabled'] ?? false))
            ->map(fn ($entry) => $entry['model'] ?? null)
            ->filter()
            ->values()
            ->all();

        $dto = PredictSatisfactionDto::instantiateFromArray([
            'model_type' => $termAnalytic->model_type,
            'model_id' => $termAnalytic->model_id,
            'term' => $termAnalytic->term,
            'start_at' => $termAnalytic->meetRecording->start_at,
            'end_at' => $termAnalytic->meetRecording->end_at,
            'api_url' => config('app.url'),
            'term_analytic_id' => $termAnalytic->getKey(),
            'satisfaction_models' => $satisfactionModels,
        ]);
        $response = Http::post(config(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.frames_microservice_url') . '/api/frames/satisfaction', $dto->toArray());

        if ($response->successful()) {
            $termAnalytic->update([
                'satisfaction_status' => SatisfactionStatusEnum::SENT,
                'satisfaction_requested_at' => Carbon::now(),
            ]);
        }

        $response->throw();
    }
}
