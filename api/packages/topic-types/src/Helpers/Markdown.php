<?php

namespace EscolaLms\TopicTypes\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Markdown
{
    public const EXT_STRING_NAME = 'extension';
    public const QUERY_STRING_NAME = 'query';
    public const PATH_STRING_NAME = 'path';

    public const SCHEME_STRING_NAME = 'scheme';
    public const PORT_STRING_NAME = 'port';
    public const USER_STRING_NAME = 'user';
    public const PASS_STRING_NAME = 'pass';
    public const FRAGMENT_STRING_NAME = 'fragment';

    protected function unParseUrl(array $parsedUrl): string
    {
        $parsedUrl[self::SCHEME_STRING_NAME] = isset($parsedUrl[self::SCHEME_STRING_NAME]) ?
            $parsedUrl[self::SCHEME_STRING_NAME] . '://' :
            '';
        $parsedUrl[self::PORT_STRING_NAME] = isset($parsedUrl[self::PORT_STRING_NAME]) ?
            ':' . $parsedUrl[self::PORT_STRING_NAME] :
            '';
        $user = isset($parsedUrl[self::USER_STRING_NAME]) ? $parsedUrl[self::USER_STRING_NAME] : '';
        $pass = isset($parsedUrl[self::PASS_STRING_NAME]) ? ':' . $parsedUrl[self::PASS_STRING_NAME] : '';
        $parsedUrl[self::PASS_STRING_NAME] = ($user || $pass) ? "$pass@"  : '';
        $parsedUrl[self::QUERY_STRING_NAME] = isset($parsedUrl[self::QUERY_STRING_NAME]) ?
            '?' . urldecode($parsedUrl[self::QUERY_STRING_NAME]) :
            '';
        $parsedUrl[self::FRAGMENT_STRING_NAME] = isset($parsedUrl[self::FRAGMENT_STRING_NAME]) ?
            '#' . $parsedUrl[self::FRAGMENT_STRING_NAME] :
            '';
        return implode($parsedUrl);
    }

    public function convertAssetPaths(string $input, string $destinationPrefix): array
    {
        $results = [];
        // https://gist.github.com/rohit00082002/2773368 might be better
        $value = preg_replace_callback(
            '/(!?)\[(.*)\]\s?\(([^)]+)\)/',
            function ($match) use ($destinationPrefix, &$results) {
                $filepath = $match[3] ?? null;
                $basename = basename($filepath);
                $destination = sprintf($destinationPrefix . '%s', $basename);

                if(str_contains($filepath, 'http') && !str_starts_with($filepath, url('/'))) {
                  return str_replace($match[3], $match[3], $match[0]);
                }

                // three scenarios
                match (true) {
                    str_contains($filepath, 'api/images/img') => $results[] = $this->convertFilePathByApiPattern($destinationPrefix, $filepath, $destination),
                    str_contains($filepath, 'storage') => $results[] = $this->convertFilePathByStorage($filepath, $destination),
                    str_contains($filepath, 'http') => $results[] = $this->convertFilePathByHttp($destinationPrefix, $filepath, $destination),
                    default => $results[] = null,
                };

                return str_replace($match[3], $destination, $match[0]);
            },
            $input
        );

        return [
            'value' => $value,
            'results' => $results,
        ];
    }

    private function convertFilePathByApiPattern(
        string $destinationPrefix,
        string $filepath,
        string &$destination
    ): array
    {
        // image is served by images API, default, just checking path
        $string = $filepath;
        $pattern = parse_url($string);
        parse_str($pattern[self::QUERY_STRING_NAME], $vars);
        $filepath = $vars['path'];
        $basename = basename($filepath);
        $destination = sprintf($destinationPrefix . '%s', $basename);
        if (!Storage::exists($destination)) {
            Storage::move($filepath, $destination);
        }
        $results = [$filepath, $destination];
        $destination = $this->unParseUrl(array_merge(
            $pattern,
            [
                self::QUERY_STRING_NAME => http_build_query(array_merge(
                    $vars,
                    [
                        'path' => urldecode($destination),
                    ]
                )),
            ]
        ));

        return $results;
    }

    private function convertFilePathByHttp(string $destinationPrefix, string $filepath, string &$destination): array
    {
        $destination = sprintf($destinationPrefix . '%s', Str::slug(basename($filepath)));
        Storage::put($destination, file_get_contents($filepath));
        $pathInfo = pathinfo($destination);
        if (!array_key_exists(self::EXT_STRING_NAME, $pathInfo) ||
            (
                array_key_exists(self::EXT_STRING_NAME, $pathInfo) &&
                !in_array(
                    $pathInfo[self::EXT_STRING_NAME],
                    ['apng', 'avif', 'gif', 'jpg', 'jpeg', 'jfif', 'pjpeg', 'pjp', 'png', 'svg', 'webp']
                )
            )
        ) {
            $info = getimagesize(Storage::path($destination));
            $ext = explode('/', $info['mime']);
            if (Storage::exists($destination . '.' . $ext[1])) {
                Storage::delete($destination . '.' . $ext[1]);
            }
            Storage::copy($destination, $destination . '.' . $ext[1]);
            $destination = $destination . '.' . $ext[1];
        }
        $destination = url('api/images/img?path=') . $destination;

        return [$filepath, $destination];
    }

    private function convertFilePathByStorage(
        string $filepath,
        string &$destination
    ): array {
        $pattern = parse_url($filepath);
        $filepath = ltrim($pattern[self::PATH_STRING_NAME], '/storage');
        Storage::move($filepath, $destination);

        return [$filepath, $destination];
    }

    public function getImagesPathsWithoutImageApi(string $input): string
    {
        return preg_replace_callback('/!\[(.*)\]\s?\((.*)()(.*)\)/', function ($match) {
            $filepath = $match[2];
            if (strpos($filepath, 'api/images/img') !== false) {
                // image is served by images API, default, just checking path
                $pattern = parse_url($filepath);
                parse_str($pattern[self::QUERY_STRING_NAME], $vars);
                $destination = $vars['path'];

                return str_replace($match[2], $destination, $match[0]);
            }

            return $match[0];
        }, $input);
    }
}
