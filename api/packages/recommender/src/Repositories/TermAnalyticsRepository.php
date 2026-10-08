<?php

namespace EscolaLms\Recommender\Repositories;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\Recommender\Dto\TermAnalyticsFilterListDto;
use EscolaLms\Recommender\Models\TermAnalytic;
use EscolaLms\Questionnaire\Models\QuestionAnswer;
use EscolaLms\Questionnaire\Models\QuestionnaireModel;
use EscolaLms\Questionnaire\Models\QuestionnaireModelType;
use EscolaLms\Recommender\Repositories\Contracts\TermAnalyticsRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TermAnalyticsRepository extends BaseRepository implements TermAnalyticsRepositoryContract
{
    public function model()
    {
        return TermAnalytic::class;
    }

    public function getFieldsSearchable()
    {
        return [];
    }

    public function findByCriteria(
        string $modelType,
        TermAnalyticsFilterListDto $criteriaDto,
        int $perPage,
        ?OrderDto $orderDto = null
    ): LengthAwarePaginator {
        $query = $this->prepareQueryWithRating($modelType);
        $query = $this->applyCriteria($query, $criteriaDto->toArray());

        if ($orderDto) {
            $query = $this->orderBy($query, $orderDto);
        }

        return $query->paginate($perPage);
    }

    public function findById(string $modelType, int $id): TermAnalytic
    {
        return $this->prepareQueryWithRating($modelType)
            ->where('ta.id', $id)
            ->firstOrFail();
    }

    private function prepareQueryWithRating(string $modelType): Builder
    {
        $modelTable = $this->resolveModelTable($modelType);

        return TermAnalytic::query()
            ->from('term_analytics as ta')
            ->join("$modelTable as m", 'm.id', '=', 'ta.model_id')
            ->leftJoin('meet_recordings as mr', 'mr.id', '=', 'ta.meet_recording_id')
            ->with('meetRecording')
            ->select([
                'ta.*',
                'm.name as model_name',
            ])
            ->where('ta.model_type', $modelType);
    }

    private function resolveModelTable(string $modelType): string
    {
        return match ($modelType) {
            'consultation' => 'consultations',
            'webinar' => 'webinars',
            default => throw new \RuntimeException('Invalid model type'),
        };
    }

    private function resolveQuestionnaireTypeTitle(string $modelType): string
    {
        return match ($modelType) {
            'consultation' => 'consultations',
            'webinar' => 'webinar',
            default => $modelType,
        };
    }

    private function orderBy(Builder $query, ?OrderDto $orderDto): Builder
    {
        if (!$orderDto || !$orderDto->getOrderBy()) {
            return $query->orderBy('ta.id', 'desc');
        }

        $column = match ($orderDto->getOrderBy()) {
            'name' => 'm.name',
            'term' => 'ta.term',
            'rating' => 'rating',
            default => 'ta.id',
        };

        return $query->orderBy($column, $orderDto->getOrder() ?? 'asc');
    }
}
