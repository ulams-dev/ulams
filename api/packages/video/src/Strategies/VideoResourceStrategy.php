<?php

namespace EscolaLms\Video\Strategies;

class VideoResourceStrategy
{
    function clientResource($object): array
    {
        return [];
    }

    function adminResource($object): array
    {
        return [];
    }

    protected function parseJson($object): ?array
    {
        $json = $object->topic->json;
        return is_array($json) ? $json : json_decode($json, true);
    }
}
