<?php

namespace Ulams\Jitsi\Services;

use Ulams\Jitsi\Dto\RecordedVideoDto;
use Ulams\Jitsi\Exceptions\InvalidJitsiFqnException;
use Ulams\Jitsi\Exceptions\RecordedVideoSaveException;
use Ulams\Jitsi\Exceptions\RecordingUrlNotAllowedException;
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

        // The fqn becomes a storage path: only plain segments are allowed.
        foreach ($folders as $folder) {
            if (!preg_match('/^[A-Za-z0-9-]+$/', $folder)) {
                throw new InvalidJitsiFqnException();
            }
        }

        if (count($folders) > 1) {
            $path = '';
            for ($i = 1; $i < count($folders); $i++) {
                $path .= "{$folders[$i]}/";
            }
        } else {
            $path = "jitsi/videos/{$folders[0]}/";
        }

        $url = $dto->getData()->getPreAuthenticatedLink();
        $extension = $this->allowedRecordingExtension($url);
        $startTimestamp = $dto->getData()->getStartTimestamp();

        if (!ctype_digit($startTimestamp)) {
            throw new RecordedVideoSaveException();
        }

        try {
            $file = $this->fileService->getFileFromUrl($url);
            Storage::put($path . $startTimestamp . '.' . $extension, $file);
        } catch (RecordingUrlNotAllowedException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RecordedVideoSaveException();
        } finally {
            if (isset($file) && is_resource($file)) {
                fclose($file);
            }
        }
    }

    /**
     * The recording link must be https, point to a configured recording host and have a video extension.
     */
    private function allowedRecordingExtension(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        if (($parts['scheme'] ?? null) !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new RecordingUrlNotAllowedException();
        }

        $allowed = false;
        foreach ((array) config('jitsi.recording_hosts', []) as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern === '') {
                continue;
            }
            if ($pattern === $host || (str_starts_with($pattern, '*.') && str_ends_with($host, substr($pattern, 1)))) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new RecordingUrlNotAllowedException();
        }

        $extension = strtolower(pathinfo($parts['path'] ?? '', PATHINFO_EXTENSION));

        if (!in_array($extension, (array) config('jitsi.recording_extensions', ['mp4', 'webm']), true)) {
            throw new RecordingUrlNotAllowedException();
        }

        return $extension;
    }
}
