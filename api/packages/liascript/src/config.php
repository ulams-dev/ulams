<?php

return [
    // Disk for assets (default: FILESYSTEM_DRIVER, the tenant bucket). Served from the content
    // origin under liascript/ (api/docs/content-origin.md).
    'disk' => env('LIASCRIPT_DISK'),
    'max_markdown_bytes' => (int) env('LIASCRIPT_MAX_MARKDOWN_KB', 2048) * 1024,
    // LiaScript SCORM build fetched by bin/fetch-player.sh (default: resources/player/build)
    'player_build_path' => env('LIASCRIPT_PLAYER_BUILD_PATH'),
    // lifetime of the content-origin player's progress token, seconds
    'progress_token_ttl' => (int) env('LIASCRIPT_PROGRESS_TOKEN_TTL', 14400),
    // editor live preview: drafts (preview-<random>.md next to the current version) live this many
    // seconds; at most preview_keep older drafts per version are kept
    'preview_ttl' => (int) env('LIASCRIPT_PREVIEW_TTL', 3600),
    'preview_keep' => (int) env('LIASCRIPT_PREVIEW_KEEP', 4),
];
