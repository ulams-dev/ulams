<?php

namespace Ulams\H5P\Testing;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Ulams\H5P\Database\Factories\H5PContentFactory;
use Ulams\H5P\Models\H5PContent;

/**
 * Http::fake() for the H5P service, backed by the h5p.contents table:
 *
 *  - POST   /h5p/contents/upload       inserts a row from the uploaded package
 *  - GET    /h5p/contents/{id}         row summary
 *  - GET    /h5p/contents/{id}/download a minimal .h5p built from the row
 *  - DELETE /h5p/contents/{id}         deletes the row
 *
 * For tests only.
 */
class H5PServiceFake
{
    public static function fake(): void
    {
        $base = rtrim((string) config('h5p.service_url'), '/') . '/h5p/';

        Http::fake([$base . '*' => fn (Request $request) => self::handle($request, $base)]);
    }

    private static function handle(Request $request, string $base)
    {
        $path = trim(substr(strtok($request->url(), '?'), strlen($base)), '/');
        $method = strtoupper($request->method());

        if ($method === 'POST' && $path === 'contents/upload') {
            return self::upload($request);
        }
        if (preg_match('#^contents/(\d+)(/download)?$#', $path, $m)) {
            $content = H5PContent::query()->find((int) $m[1]);
            if (!$content) {
                return Http::response(['success' => false, 'message' => 'Content not found.'], 404);
            }
            if ($method === 'GET' && !empty($m[2])) {
                $file = H5PContentFactory::toPackage($content);
                $body = file_get_contents($file);
                @unlink($file);

                return Http::response($body, 200, ['Content-Type' => 'application/zip']);
            }
            if ($method === 'GET') {
                return Http::response(['success' => true, 'data' => self::summary($content), 'message' => '']);
            }
            if ($method === 'DELETE') {
                DB::table('h5p.contents')->where('id', $content->getKey())->delete();

                return Http::response(['success' => true, 'data' => ['contentId' => (string) $content->getKey()], 'message' => 'Content deleted']);
            }
        }

        return Http::response(['success' => false, 'message' => 'Not found.'], 404);
    }

    private static function upload(Request $request)
    {
        $part = collect($request->data())->firstWhere('name', 'h5p_file');
        if (!$part) {
            return Http::response(['success' => false, 'message' => 'Upload exactly one .h5p file in the multipart field "h5p_file".'], 422);
        }
        $contents = $part['contents'];
        if (is_resource($contents)) {
            rewind($contents);
            $contents = stream_get_contents($contents);
        } elseif (!is_string($contents)) {
            $contents = (string) Utils::streamFor($contents);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'h5p-fake-upload-');
        file_put_contents($tmp, $contents);
        try {
            $content = H5PContentFactory::fromPackage($tmp);
        } catch (\Throwable $e) {
            return Http::response(['success' => false, 'message' => $e->getMessage()], 422);
        } finally {
            @unlink($tmp);
        }

        return Http::response([
            'success' => true,
            'data' => [
                'contentId' => (string) $content->getKey(),
                'metadata' => $content->metadata,
                'installedLibraries' => [],
            ],
            'message' => 'Content uploaded',
        ], 201);
    }

    private static function summary(H5PContent $content): array
    {
        return [
            'id' => (string) $content->getKey(),
            'title' => $content->title,
            'mainLibrary' => $content->main_library,
            'libraryVersion' => $content->library_version,
            'userId' => $content->user_id,
            'createdAt' => optional($content->created_at)->toISOString(),
            'updatedAt' => optional($content->updated_at)->toISOString(),
            'metadata' => $content->metadata,
        ];
    }
}
