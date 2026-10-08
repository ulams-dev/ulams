<?php

namespace EscolaLms\Cmi5\Services;

use EscolaLms\Cmi5\Models\Cmi5;
use EscolaLms\Cmi5\Repositories\Contracts\Cmi5AuRepositoryContract;
use EscolaLms\Cmi5\Repositories\Contracts\Cmi5RepositoryContract;
use EscolaLms\Cmi5\Services\Contracts\Cmi5ServiceContract;
use EscolaLms\Lrs\Services\Contracts\LrsServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class Cmi5Service implements Cmi5ServiceContract
{
    private Cmi5RepositoryContract $cmi5Repository;
    private Cmi5AuRepositoryContract $cmi5AuRepository;
    private LrsServiceContract $lrsService;

    public function __construct(
        Cmi5RepositoryContract $cmi5Repository,
        Cmi5AuRepositoryContract $cmi5AuRepository,
        LrsServiceContract $lrsService)
    {
        $this->cmi5Repository = $cmi5Repository;
        $this->cmi5AuRepository = $cmi5AuRepository;
        $this->lrsService = $lrsService;
    }

    public function getCmi5s(?int $perPage): LengthAwarePaginator
    {
        return $this->cmi5Repository->paginate($perPage ?? 15);
    }

    public function getPlayerData(int $cmi5AuId, string $token, ?int $courseId = null, ?int $topicId = null): array
    {
        $cmi5Au = $this->cmi5AuRepository->find($cmi5AuId);
        $cmi5 = $cmi5Au->cmi5;
        $launchParams = $this->lrsService->launchParams($token, $courseId, $topicId);

        return [
            'url' => Storage::disk(config('escolalms_cmi5.disk'))
                ->url('cmi5/' . $cmi5->getKey() . '/' . $cmi5Au->url . '?' . $launchParams['url'])
        ];
    }

    public function delete(Cmi5 $cmi5): void
    {
        $this->cmi5AuRepository->deleteWhere(['cmi5_id' => $cmi5->getKey()]);
        $this->cmi5Repository->delete($cmi5->getKey());

        $disk = Storage::disk(config('escolalms_cmi5.disk'));
        $path = 'cmi5/' . $cmi5->getKey();

        if ($disk->exists($path)) {
            $disk->deleteDirectory($path);
        }
    }
}
