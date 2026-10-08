<?php

namespace Ulams\Courses\Repositories\Contracts;

use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Models\TopicResource;
use Illuminate\Http\UploadedFile;

interface TopicResourceRepositoryContract extends BaseRepositoryContract
{
    public function storeUploadedResourceForTopic(Topic $topic, $file): TopicResource;

    public function rename(int $id, string $name): bool;
    public function renameModel(TopicResource $model, string $name): bool;
}
