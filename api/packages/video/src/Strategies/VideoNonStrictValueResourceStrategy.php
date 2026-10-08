<?php

namespace EscolaLms\Video\Strategies;

use Illuminate\Support\Facades\Storage;

class VideoNonStrictValueResourceStrategy extends VideoResourceStrategy
{
    function clientResource($object): array
    {
        $json = $this->parseJson($object);
        $topicable = $object->topic->topicable;
        $value = $json['ffmpeg']['path'] ?? $topicable->value;

        return [
            'value' => $value,
            'url' => Storage::url($value),
        ];
    }

    function adminResource($object): array
    {
        $json = $this->parseJson($object);

        return [
            'hls' => $json['ffmpeg']['path'] ?? null,
            'hls_url' => isset($json['ffmpeg']['path']) ? Storage::url($json['ffmpeg']['path']) : null,
        ];
    }
}
