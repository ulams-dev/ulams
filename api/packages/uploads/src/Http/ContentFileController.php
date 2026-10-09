<?php

namespace Ulams\Uploads\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;
use Ulams\Uploads\Exceptions\UploadRejected;
use Ulams\Uploads\Zip\ZipInspector;

/**
 * Package files (SCORM, cmi5, LiaScript, ...) for the tenant content origin
 * (api/docs/content-origin.md). The content origin's proxy calls GET /api/content/<prefix>/<path>
 * on the tenant API host with the `X-Ulams-Content-Origin` header; the file comes from the disk
 * of that package type, whether it is a local disk or a bucket. Without a configured content
 * origin, or without the header (a browser opening the API URL directly), the answer is 404, so
 * package HTML never runs on the API origin through this route.
 */
class ContentFileController extends Controller
{
    public const HEADER = 'X-Ulams-Content-Origin';

    /** finfo reports JavaScript and CSS as text/plain; packages need the real types. */
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
        'md' => 'text/markdown; charset=UTF-8',
        'txt' => 'text/plain; charset=UTF-8',
        'wasm' => 'application/wasm',
    ];

    public function show(Request $request, string $path): Response
    {
        abort_unless($this->fromContentOrigin($request), 404);

        try {
            $normalised = ZipInspector::normalise($path);
        } catch (UploadRejected) {
            abort(404);
        }
        $prefix = explode('/', $normalised, 2)[0];
        $disks = (array) config('ulams_uploads.content_disks', []);
        abort_unless($normalised !== $prefix && array_key_exists($prefix, $disks), 404);

        $diskName = (string) (config((string) $disks[$prefix]) ?: config('filesystems.default'));
        $disk = Storage::disk($diskName);
        abort_unless($disk->exists($normalised), 404);

        $extension = strtolower(pathinfo($normalised, PATHINFO_EXTENSION));
        $headers = [
            'Content-Type' => self::TYPES[$extension] ?? (MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream'),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => str_contains($normalised, '/_player/') ? 'no-cache' : 'public, max-age=3600',
        ];

        if ((config("filesystems.disks.{$diskName}.driver")) === 'local') {
            return response()->file($disk->path($normalised), $headers);
        }

        $headers['Content-Length'] = (string) $disk->size($normalised);

        return response()->stream(function () use ($disk, $normalised) {
            $stream = $disk->readStream($normalised);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, $headers);
    }

    private function fromContentOrigin(Request $request): bool
    {
        return trim((string) (config('ulams_uploads.content_origin') ?: config('scorm.content_origin'))) !== ''
            && $request->headers->get(self::HEADER) === '1';
    }
}
