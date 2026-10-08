<?php

namespace Ulams\H5P\Services;

use Illuminate\Support\Facades\Log;
use Ulams\H5P\Dtos\H5PContentCriteriaDto;
use Ulams\H5P\Exceptions\H5PServiceException;
use Ulams\H5P\Models\H5PContent;
use Ulams\H5P\Services\Contracts\H5PContentServiceContract;
use Ulams\H5P\Services\Contracts\H5PServiceClientContract;

class H5PContentService implements H5PContentServiceContract
{
    public const ORDER_COLUMNS = ['id', 'title', 'main_library', 'library', 'user_id', 'created_at', 'updated_at', 'count_h5p'];

    private H5PServiceClientContract $client;

    public function __construct(H5PServiceClientContract $client)
    {
        $this->client = $client;
    }

    public function list(H5PContentCriteriaDto $criteria, int $perPage, string $orderBy = 'id', string $order = 'desc')
    {
        $query = H5PContent::query()
            ->select(['id', 'title', 'main_library', 'library_version', 'user_id', 'created_at', 'updated_at'])
            ->withTopicCount();

        if ($criteria->title !== null) {
            $query->where('title', 'ILIKE', '%' . addcslashes($criteria->title, '\\%_') . '%');
        }
        if ($criteria->mainLibrary !== null) {
            $query->where('main_library', $criteria->mainLibrary);
        }
        if ($criteria->userId !== null) {
            $query->ownedBy($criteria->userId);
        }

        $orderBy = in_array($orderBy, self::ORDER_COLUMNS, true) ? $orderBy : 'id';
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';
        if ($orderBy === 'library') {
            $query->orderBy('main_library', $order)->orderBy('library_version', $order);
        } else {
            $query->orderBy($orderBy, $order);
        }
        if ($orderBy !== 'id') {
            $query->orderBy('id', 'desc');
        }

        return $perPage === 0 ? $query->get() : $query->paginate($perPage);
    }

    public function deleteUnused(): array
    {
        $ids = [];
        $failed = [];
        foreach (H5PContent::query()->unused()->orderBy('id')->pluck('id') as $id) {
            try {
                $this->client->delete((int) $id);
                $ids[] = (int) $id;
            } catch (H5PServiceException $e) {
                Log::warning('Deleting unused H5P content failed', ['id' => $id, 'error' => $e->getMessage()]);
                $failed[] = ['id' => (int) $id, 'message' => $e->getMessage()];
            }
        }

        return ['ids' => $ids, 'failed' => $failed];
    }
}
