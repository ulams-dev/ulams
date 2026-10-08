<?php

namespace EscolaLms\Video\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VideoProcessStateResource extends JsonResource
{
    public function toArray($request)
    {
        $topic = $this->topic;

        return [
            'id' => $this->id,
            'topic_id' => isset($topic) ? $topic->id : null,
            'topic_title' => isset($topic) ? $topic->title : null,
            'json' =>  isset($topic) ? $topic->json : null,
            'state' => isset($topic) ? ($topic->json['ffmpeg']['state'] ?? null) : null,
        ];
    }
}
