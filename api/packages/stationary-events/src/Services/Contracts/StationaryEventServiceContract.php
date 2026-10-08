<?php

namespace Ulams\StationaryEvents\Services\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Ulams\StationaryEvents\Models\StationaryEvent;
use Illuminate\Database\Eloquent\Builder;

interface StationaryEventServiceContract
{
    public function getStationaryEventList(OrderDto $orderDto, array $search = [], bool $onlyActive = false): Builder;
    public function create(array $data): StationaryEvent;
    public function update(StationaryEvent $stationaryEvent, array $data): StationaryEvent;
    public function delete(StationaryEvent $stationaryEvent): bool;
    public function addAccessForUsers(StationaryEvent $stationaryEvent, array $users = []): void;
    public function getStationaryEventListForCurrentUser(OrderDto $orderDto, array $search = []): Builder;
}
