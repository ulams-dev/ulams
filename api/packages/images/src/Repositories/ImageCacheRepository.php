<?php

namespace Ulams\Images\Repositories;

use Ulams\Core\Repositories\BaseRepository;
use Ulams\Images\Models\ImageCache;
use Ulams\Images\Repositories\Contracts\ImageCacheRepositoryContract;

class ImageCacheRepository extends BaseRepository implements ImageCacheRepositoryContract
{
    protected $fieldSearchable = [
        'path',
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return ImageCache::class;
    }
}
