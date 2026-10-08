<?php

namespace Ulams\StationaryEvents\Services;

use Ulams\Auth\Models\User;
use Ulams\Categories\Repositories\Criteria\InCategoriesOrChildrenCriterion;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Repositories\Criteria\Primitives\DateCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\EqualCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\InCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\LikeCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\WhereCriterion;
use Ulams\Courses\Repositories\Criteria\Primitives\OrderCriterion;
use Ulams\Files\Helpers\FileHelper;
use Ulams\StationaryEvents\Enum\ConstantEnum;
use Ulams\StationaryEvents\Enum\StationaryEventStatusEnum;
use Ulams\StationaryEvents\Events\StationaryEventAssigned;
use Ulams\StationaryEvents\Events\StationaryEventAuthorAssigned;
use Ulams\StationaryEvents\Events\StationaryEventAuthorUnassigned;
use Ulams\StationaryEvents\Events\StationaryEventUnassigned;
use Ulams\StationaryEvents\Models\StationaryEvent;
use Ulams\StationaryEvents\Repositories\Contracts\StationaryEventRepositoryContract;
use Ulams\StationaryEvents\Services\Contracts\StationaryEventServiceContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class StationaryEventService implements StationaryEventServiceContract
{
    private StationaryEventRepositoryContract $stationaryEventRepository;

    public function __construct(StationaryEventRepositoryContract $stationaryEventRepository)
    {
        $this->stationaryEventRepository = $stationaryEventRepository;
    }

    public function getStationaryEventList(OrderDto $orderDto, array $search = [], bool $onlyActive = false): Builder
    {
        $criteria = $this->prepareListCriteria($orderDto, $search);

        if ($onlyActive) {
            $criteria[] = new DateCriterion('started_at', now()->format('Y-m-d H:i:s'), '>=');
            $criteria[] = new InCriterion('status', [StationaryEventStatusEnum::PUBLISHED_UNACTIVATED, StationaryEventStatusEnum::PUBLISHED]);
        }

        return $this->stationaryEventRepository
            ->allQueryBuilder($criteria)
            ->with(['authors']);
    }

    public function getStationaryEventListForCurrentUser(OrderDto $orderDto, array $search = []): Builder
    {
        $criteria = $this->prepareListCriteria($orderDto, $search);

        return $this->stationaryEventRepository
            ->forCurrentUser($criteria)
            ->with('authors');
    }

    public function create(array $data): StationaryEvent
    {
        $stationaryEvent = $this->stationaryEventRepository->create($data);
        $this->syncAuthors($stationaryEvent, $data['authors'] ?? []);

        if (isset($data['categories']) && is_array($data['categories'])) {
            $stationaryEvent->categories()->sync($data['categories']);
        }

        if (isset($data['image'])) {
            $stationaryEvent->image_path = FileHelper::getFilePath($data['image'], ConstantEnum::DIRECTORY . '/' . $stationaryEvent->getKey() . '/images');
            $stationaryEvent->save();
        }

        return $stationaryEvent;
    }

    public function update(StationaryEvent $stationaryEvent, array $data): StationaryEvent
    {
        if (isset($data['image'])) {
            $data['image_path'] = FileHelper::getFilePath($data['image'], ConstantEnum::DIRECTORY . '/' . $stationaryEvent->getKey() . '/images');
        }

        $stationaryEvent = $this->stationaryEventRepository->update($data, $stationaryEvent->getKey());
        $this->syncAuthors($stationaryEvent, $data['authors'] ?? []);

        if (isset($data['categories']) && is_array($data['categories'])) {
            $stationaryEvent->categories()->sync($data['categories']);
        }

        return $stationaryEvent;
    }

    public function delete(StationaryEvent $stationaryEvent): bool
    {
        return $this->stationaryEventRepository->delete($stationaryEvent->getKey()) ?? false;
    }

    public function addAccessForUsers(StationaryEvent $stationaryEvent, array $users = []): void
    {
        $result = $stationaryEvent->users()->syncWithoutDetaching($users);
        $this->dispatchEventForUsersAttachedToStationaryEvent($stationaryEvent, $result['attached']);
    }

    private function syncAuthors(StationaryEvent $stationaryEvent, array $authors = []): void
    {
        $syncResult = $stationaryEvent->authors()->sync($authors);

        foreach ($syncResult['attached'] as $attached) {
            event(new StationaryEventAuthorAssigned(User::find($attached), $stationaryEvent));
        }

        foreach ($syncResult['detached'] as $attached) {
            event(new StationaryEventAuthorUnassigned(User::find($attached), $stationaryEvent));
        }
    }

    private function dispatchEventForUsersAttachedToStationaryEvent(StationaryEvent $stationaryEvent, array $users = []): void
    {
        foreach ($users as $attached) {
            $user = is_int($attached) ? User::find($attached) : $attached;
            event(new StationaryEventAssigned($user, $stationaryEvent));
        }
    }

    private function dispatchEventForUsersDetachedToStationaryEvent(StationaryEvent $stationaryEvent, array $users = []): void
    {
        foreach ($users as $detached) {
            $user = is_int($detached) ? User::find($detached) : $detached;
            event(new StationaryEventUnassigned($user, $stationaryEvent));
        }
    }

    private function prepareListCriteria(OrderDto $orderDto, array $search = []): array
    {
        $criteria = [];

        if (!is_null($orderDto->getOrder())) {
            $criteria[] = new OrderCriterion($orderDto->getOrderBy(), $orderDto->getOrder());
        }

        if (isset($search['name'])) {
            $like = DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql' ? 'ILIKE' : 'LIKE';
            $criteria[] = new WhereCriterion('name', '%' . $search['name'] . '%', $like);
        }

        if (isset($search['status'])) {
            $criteria[] = new EqualCriterion('status', $search['status']);
        }

        if (isset($search['categories'])) {
            $criteria[] = new InCategoriesOrChildrenCriterion(null, $search['categories']);
        }

        return $criteria;
    }
}
