<?php

namespace Ulams\Cmi5\Services;

use Ulams\Cmi5\Models\Cmi5;
use Ulams\Cmi5\Repositories\Contracts\Cmi5AuRepositoryContract;
use Ulams\Cmi5\Repositories\Contracts\Cmi5RepositoryContract;
use Ulams\Cmi5\Services\Contracts\Cmi5ServiceContract;
use Ulams\Lrs\Services\Contracts\LrsServiceContract;
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

    public function getPlayerData(int $cmi5AuId, ?int $courseId = null, ?int $topicId = null): array
    {
        $cmi5Au = $this->cmi5AuRepository->find($cmi5AuId);
        $cmi5 = $cmi5Au->cmi5;
        $launchParams = $this->lrsService->launchParams($courseId, $topicId, $cmi5Au->getKey());

        // the LMS writes these before the AU starts (cmi5 specification)
        $launchParams = $this->lrsService->saveState($launchParams);
        $this->lrsService->saveAgent($launchParams);

        $origin = $this->contentOrigin();
        $entry = (string) $cmi5Au->url;
        $path = 'cmi5/' . $cmi5->getKey() . '/' . ltrim($entry, '/');

        if (preg_match('#^https?://#i', $entry)) {
            $base = $entry; // an AU hosted elsewhere
        } elseif ($origin !== null) {
            $base = $origin . '/' . $path;
        } else {
            $base = Storage::disk(config('ulams_cmi5.disk'))->url($path);
        }

        return [
            'url' => $base . (str_contains($base, '?') ? '&' : '?') . $launchParams['url'],
            'origin' => $origin,
            'registration' => $launchParams['registration'],
        ];
    }

    /** The tenant content origin (ADR 0014), null when the tenant has none. */
    public function contentOrigin(): ?string
    {
        $origin = trim((string) (config('ulams_uploads.content_origin') ?: config('scorm.content_origin')));

        return $origin === '' ? null : rtrim($origin, '/');
    }

    public function delete(Cmi5 $cmi5): void
    {
        $this->cmi5AuRepository->deleteWhere(['cmi5_id' => $cmi5->getKey()]);
        $this->cmi5Repository->delete($cmi5->getKey());

        $disk = Storage::disk(config('ulams_cmi5.disk'));
        $path = 'cmi5/' . $cmi5->getKey();

        if ($disk->exists($path)) {
            $disk->deleteDirectory($path);
        }
    }
}
