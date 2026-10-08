<?php

namespace Ulams\Jitsi\Services;

use Ulams\Jitsi\Dto\RecordedVideoDto;
use Ulams\Jitsi\Exceptions\InvalidJitsiFqnException;
use Ulams\Jitsi\Exceptions\RecordedVideoSaveException;
use Ulams\Jitsi\Services\Contracts\JitsiVideoServiceContract;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class JitsiVideoService implements JitsiVideoServiceContract
{
    public function __construct(
        protected FileService $fileService,
    ) {}

    public function recordedVideo(RecordedVideoDto $dto): void
    {
        $appId = config('jitsi.app_id');
        if (!Str::startsWith($dto->getFqn(), $appId . '/')) {
            throw new InvalidJitsiFqnException();
        }

        $folders = explode('_', Str::after($dto->getFqn(), $appId . '/'));

        if (count($folders) > 1) {
            $path = '';
            for ($i = 1; $i < count($folders); $i++) {
                $path .= "{$folders[$i]}/";
            }
        } else {
            $path = "jitsi/videos/{$folders[0]}/";
        }

        try {
            $file = $this->fileService->getFileFromUrl($dto->getData()->getPreAuthenticatedLink());
            Storage::put($path . $dto->getData()->getStartTimestamp() . '.' . Str::afterLast($dto->getData()->getPreAuthenticatedLink(), '.'), $file);
        } catch (Throwable $e) {
            throw new RecordedVideoSaveException();
        }
    }
}
