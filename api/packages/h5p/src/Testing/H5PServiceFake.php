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
 *  - POST   /h5p/contents              inserts a row from {library, params:{params, metadata}}; the library must be in
 *                                      {@see self::$libraries}, otherwise 422 like the service
 *  - PATCH  /h5p/contents/{id}         replaces the row's parameters and metadata
 *  - GET    /h5p/libraries             the installed libraries ({@see self::$libraries})
 *  - POST   /h5p/contents/upload       inserts a row from the uploaded package
 *  - GET    /h5p/contents/{id}         row summary
 *  - GET    /h5p/contents/{id}/download a minimal .h5p built from the row
 *  - DELETE /h5p/contents/{id}         deletes the row
 *  - POST   /h5p/contents/orphans/delete nothing to sweep (rows have no files here)
 *
 * For tests only.
 */
class H5PServiceFake
{
    /** @var array<string,string> installed libraries: machine name => "major.minor" */
    public static array $libraries = ['H5P.Blanks' => '1.14', 'H5P.DragText' => '1.10', 'H5P.Dialogcards' => '1.9', 'H5P.MultiChoice' => '1.16'];

    public static function fake(): void
    {
        $base = rtrim((string) config('h5p.service_url'), '/') . '/h5p/';

        Http::fake([$base . '*' => fn (Request $request) => self::handle($request, $base)]);
    }

    private static function handle(Request $request, string $base)
    {
        $path = trim(substr(strtok($request->url(), '?'), strlen($base)), '/');
        $method = strtoupper($request->method());

        if ($method === 'GET' && $path === 'libraries') {
            return Http::response(array_map(fn (string $name, string $version) => [
                'title' => $name, 'machineName' => $name, 'majorVersion' => (int) explode('.', $version)[0], 'minorVersion' => (int) explode('.', $version)[1],
                'patchVersion' => 0, 'runnable' => true,
            ], array_keys(self::$libraries), array_values(self::$libraries)));
        }
        if ($method === 'POST' && $path === 'contents') {
            return self::save($request, null);
        }
        if ($method === 'PATCH' && preg_match('#^contents/(\d+)$#', $path, $m)) {
            return H5PContent::query()->find((int) $m[1]) ? self::save($request, (int) $m[1]) : Http::response(['success' => false, 'message' => 'Content not found.'], 404);
        }
        if ($method === 'POST' && $path === 'contents/upload') {
            return self::upload($request);
        }
        if ($method === 'POST' && $path === 'contents/orphans/delete') {
            return Http::response(['success' => true, 'data' => ['contentIds' => [], 'files' => 0], 'message' => '']);
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

    private static function save(Request $request, ?int $id)
    {
        $body = $request->data();
        $library = (string) ($body['library'] ?? '');
        [$main, $version] = array_pad(explode(' ', $library, 2), 2, '');
        if ($main === '' || !isset(self::$libraries[$main])) {
            return Http::response(['success' => false, 'message' => "Library {$library} is not installed."], 422);
        }
        $params = $body['params']['params'] ?? null;
        $metadata = $body['params']['metadata'] ?? null;
        if (!is_array($params) || !is_array($metadata)) {
            return Http::response(['success' => false, 'message' => 'Malformed request: expected {library, params: {params, metadata}}.'], 400);
        }
        $title = (string) ($metadata['title'] ?? '');
        $metadata += ['mainLibrary' => $main, 'embedTypes' => ['div'], 'preloadedDependencies' => [['machineName' => $main, 'majorVersion' => explode('.', $version)[0] ?? '1', 'minorVersion' => explode('.', $version)[1] ?? '0']]];
        $row = ['title' => $title, 'main_library' => $main, 'library_version' => $version, 'metadata' => json_encode($metadata), 'parameters' => json_encode($params), 'updated_at' => now()];
        if ($id === null) {
            $id = DB::table('h5p.contents')->insertGetId($row);
        } else {
            DB::table('h5p.contents')->where('id', $id)->update($row);
        }

        return Http::response(['success' => true, 'data' => ['contentId' => (string) $id, 'metadata' => $metadata], 'message' => $request->method() === 'POST' ? 'Content created' : 'Content updated'], $request->method() === 'POST' ? 201 : 200);
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
