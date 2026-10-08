<?php

namespace Ulams\Scorm\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Peopleaps\Scorm\Model\ScormScoModel;
use Ulams\Scorm\Services\Contracts\ScormContentServiceContract;
use Ulams\Scorm\Services\Contracts\ScormServiceContract;
use Ulams\Scorm\Services\Contracts\ScormTrackServiceContract;

/**
 * SCORM playback from the per-tenant content origin: the player page, scorm-again and the
 * package all live on `<slug>.content.<base>`, so the SCO and the SCORM API are same-origin and
 * nothing on that origin can reach the learner's session. See api/docs/content-origin.md.
 */
class ScormContentService implements ScormContentServiceContract
{
    public const PLAYER_DIR = 'scorm/_player';

    private const PLAYER_FILES = [
        'player.html' => __DIR__ . '/../../resources/content-player/player.html',
        'player.js' => __DIR__ . '/../../resources/content-player/player.js',
        'scorm-again.min.js' => __DIR__ . '/../../resources/js/vendor/scorm-again/scorm-again.min.js',
    ];

    public function __construct(
        private readonly ScormServiceContract $scormService,
        private readonly ScormTrackServiceContract $trackService,
    ) {
    }

    public function contentOrigin(): ?string
    {
        $origin = trim((string) (config('scorm.content_origin') ?: config('ulams_uploads.content_origin')));

        return $origin === '' ? null : rtrim($origin, '/');
    }

    public function launch(string $scoUuid, int $userId, string $apiBaseUrl): ?array
    {
        $origin = $this->contentOrigin();
        if ($origin === null) {
            return null;
        }

        $this->scormService->getScoByUuid($scoUuid); // 404 for unknown SCOs
        $this->publishPlayer();

        $token = TrackingToken::issue($userId, $scoUuid, (int) config('scorm.tracking_token_ttl', 14400));
        $fragment = http_build_query([
            'api' => rtrim($apiBaseUrl, '/'),
            'sco' => $scoUuid,
            'token' => $token,
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'url' => $origin . '/' . self::PLAYER_DIR . '/player.html#' . $fragment,
            'origin' => $origin,
            'expires_at' => date(DATE_ATOM, (int) TrackingToken::expiresAt($token)),
        ];
    }

    public function launchData(string $scoUuid, int $userId): array
    {
        /** @var ScormScoModel $sco */
        $sco = $this->scormService->getScoViewDataByUuid($scoUuid, $userId);
        $scorm = $sco->scorm;

        return [
            'uuid' => $sco->uuid,
            'title' => $sco->title,
            'version' => $scorm->version,
            // relative to the content origin, which serves the bucket's package paths
            'entry_url' => '/scorm/' . $scorm->version . '/' . $scorm->uuid . '/' . $sco->entry_url . $sco->sco_parameters,
            'cmi' => $sco['player']->cmi ?? [],
            'player' => ['logLevel' => 1, 'autoProgress' => true],
        ];
    }

    public function track(string $scoUuid, int $userId, mixed $cmi): void
    {
        $this->trackService->updateScoTracking($scoUuid, $userId, $cmi);
    }

    public function publishPlayer(bool $force = false): void
    {
        $hash = sha1(implode('', array_map('sha1_file', self::PLAYER_FILES)));
        $cacheKey = 'scorm_content_player_' . config('scorm.disk');
        if (!$force && Cache::get($cacheKey) === $hash) {
            return;
        }

        $disk = Storage::disk(config('scorm.disk'));
        foreach (self::PLAYER_FILES as $name => $source) {
            $disk->put(self::PLAYER_DIR . '/' . $name, (string) file_get_contents($source));
        }
        Cache::forever($cacheKey, $hash);
    }
}
