<?php

namespace Ulams\Adapt\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Throwable;
use Ulams\Adapt\Models\AdaptSource;
use Ulams\Scorm\Services\Contracts\ScormServiceContract;

/**
 * Sends a source version to the isolated build worker (ADR 0013: POST <builder>/build with the
 * JSON, answer: a SCORM zip) and imports the zip through the SCORM upload (Path A: upload guard,
 * safe extraction, labelled adapt). One build at a time per source.
 */
class BuildAdaptSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /** Above ADAPT_BUILDER_TIMEOUT; the builder queue's retry_after is higher still (ADR 0083). */
    public int $timeout = 900;

    public function __construct(public readonly int $sourceId, public readonly int $version)
    {
        if ($c = config('ulams_adapt.queue_connection')) {
            $this->onConnection($c);
        }
        if ($q = config('ulams_adapt.queue')) {
            $this->onQueue($q);
        }
    }

    public function handle(ScormServiceContract $scorm): void
    {
        $source = AdaptSource::query()->find($this->sourceId);
        $version = $source?->version($this->version);
        if ($source === null || $version === null) {
            return;
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'adapt-build-') . '.zip';
        try {
            $response = Http::timeout((int) config('ulams_adapt.builder_timeout', 300))
                ->withHeaders(array_filter(['X-Internal-Token' => config('ulams_adapt.builder_token')]))
                ->accept('application/zip')
                ->post(rtrim((string) config('ulams_adapt.builder_url'), '/') . '/build', [
                    'id' => "{$source->getKey()}-v{$version->version}",
                    'source' => $version->source,
                ]);
            if (!$response->successful()) {
                $error = $response->json('error') ?? $response->json('message') ?? ('HTTP ' . $response->status());
                throw new \RuntimeException('The build worker rejected the course: ' . (is_string($error) ? $error : json_encode($error)));
            }
            file_put_contents($zipPath, $response->body());

            $result = $scorm->uploadScormArchive(new UploadedFile($zipPath, "adapt-{$source->getKey()}-v{$version->version}.zip", null, null, true));
            $model = $result['model'] ?? null;
            if ($model !== null && $model->source_format !== 'adapt') {
                $model->source_format = 'adapt';
                $model->save();
            }

            $source->update([
                'status' => AdaptSource::BUILT,
                'built_version' => $version->version,
                'scorm_id' => $model?->getKey(),
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            $source->update(['status' => AdaptSource::FAILED, 'last_error' => mb_substr($e->getMessage(), 0, 2000)]);
        } finally {
            @unlink($zipPath);
            @unlink(substr($zipPath, 0, -4));
        }
    }
}
