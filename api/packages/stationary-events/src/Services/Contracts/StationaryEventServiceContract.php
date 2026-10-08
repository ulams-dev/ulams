<?php

namespace EscolaLms\StationaryEvents\Services\Contracts;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\StationaryEvents\Models\StationaryEvent;
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
