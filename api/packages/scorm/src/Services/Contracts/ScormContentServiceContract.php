<?php

namespace Ulams\Scorm\Services\Contracts;

interface ScormContentServiceContract
{
    /** The tenant content origin (no trailing slash), or null when it is not configured. */
    public function contentOrigin(): ?string;

    /**
     * Issues a SCO-scoped tracking token and returns the player URL on the content origin,
     * or null when no content origin is configured (callers fall back to the legacy player).
     *
     * @return array{url: string, origin: string, expires_at: string}|null
     */
    public function launch(string $scoUuid, int $userId, string $apiBaseUrl): ?array;

    /** Launch data for the content-origin player: version, entry URL and the learner's CMI. */
    public function launchData(string $scoUuid, int $userId): array;

    public function track(string $scoUuid, int $userId, mixed $cmi): void;

    /** Writes player.html, player.js and scorm-again to scorm/_player on the SCORM disk. */
    public function publishPlayer(bool $force = false): void;
}
