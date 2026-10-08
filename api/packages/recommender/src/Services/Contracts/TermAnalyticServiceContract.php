<?php

namespace EscolaLms\Recommender\Services\Contracts;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Recommender\Dto\PageDto;
use EscolaLms\Recommender\Dto\SatisfactionDto;
use EscolaLms\Recommender\Dto\TermAnalyticsFilterListDto;
use EscolaLms\Recommender\Models\AggregatedFrame;
use EscolaLms\Recommender\Models\TermAnalytic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface TermAnalyticServiceContract
{
    public function updateTermAnalytic(string $modelType, int $modelId, Carbon $term, Carbon $startAt): void;
    public function rebuildTermAnalytic(string $modelType, int $modelId, Carbon $term): void;
    public function termAnalyticsList(string $modelType, TermAnalyticsFilterListDto $criteriaDto, PageDto $pageDto, OrderDto $orderDto): LengthAwarePaginator;
    public function termAnalytic(string $modelType, int $id): TermAnalytic;
    public function modelAnalytics(string $modelType, int $modelId, ?int $term = null): Collection|AggregatedFrame;
    public function modelAnalyticsForTerm(int $termAnalyticId): AggregatedFrame;
    public function aggregatedFrames(int $termId, int $interval): Collection;
    public function saveSatisfaction(SatisfactionDto $dto): void;
    public function predictSatisfaction(TermAnalytic $termAnalytic): void;
}
