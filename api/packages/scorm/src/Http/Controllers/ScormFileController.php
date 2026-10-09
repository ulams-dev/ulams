<?php

namespace Ulams\Scorm\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Mime\MimeTypes;

/**
 * Serves extracted SCORM package files (`<disk url>/scorm/...`) when the SCORM disk is a local
 * disk. Each tenant has its own storage directory, and nothing else (no web server root, no
 * `public/storage` link) serves it, so without this route the player's iframe gets a 404.
 * With an S3 disk the files come straight from the bucket and this route is not registered.
 * Legacy path: when the tenant has a content origin this route answers 404 and the content
 * origin serves the files instead.
 */
class ScormFileController extends Controller
{
    /** Content types by extension: finfo reports JavaScript and CSS as text/plain. */
    private const TYPES = [
        'html' => 'text/html; charset=UTF-8',
        'htm' => 'text/html; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'mjs' => 'text/javascript; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'xsd' => 'application/xml',
        'svg' => 'image/svg+xml',
        'txt' => 'text/plain; charset=UTF-8',
    ];

    public function show(string $path): BinaryFileResponse
    {
        // With a tenant content origin, package files (third-party JavaScript) are only served there
        // (GET /api/content/scorm/..., api/docs/content-origin.md), never on the API origin.
        abort_if(trim((string) (config('scorm.content_origin') ?: config('ulams_uploads.content_origin'))) !== '', 404);

        abort_if($path === '' || str_contains($path, "\0") || preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $path), 404);

        $disk = Storage::disk((string) config('scorm.disk'));
        $root = realpath($disk->path('scorm'));
        $file = realpath($disk->path('scorm/' . $path));
        abort_unless(
            $root !== false && $file !== false && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file),
            404
        );

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $type = self::TYPES[$extension] ?? (MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream');

        return response()->file($file, [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
