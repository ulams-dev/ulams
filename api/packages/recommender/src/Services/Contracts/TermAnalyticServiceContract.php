<?php

namespace Ulams\Recommender\Services\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Recommender\Dto\PageDto;
use Ulams\Recommender\Dto\SatisfactionDto;
use Ulams\Recommender\Dto\TermAnalyticsFilterListDto;
use Ulams\Recommender\Models\AggregatedFrame;
use Ulams\Recommender\Models\TermAnalytic;
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
