<?php

namespace EscolaLms\Video\Strategies;

use Illuminate\Support\Facades\Storage;

class VideoEnableProcessingStrategy extends VideoResourceStrategy
{
    function clientResource($object): array
    {
        $json = $this->parseJson($object);

        return [
            'value' => $json['ffmpeg']['path'] ?? null,
            'url' => isset($json['ffmpeg']['path']) ? Storage::url($json['ffmpeg']['path']) : null,
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
