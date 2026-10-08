<?php

namespace EscolaLms\TopicTypes\Database\Factories\TopicContent\Components;

use EscolaLms\Courses\Models\Topic;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Illuminate\Http\File;

class FileHelper
{
    public static function uploadFile(int $topicId, string $name, string $ext = 'jpg'): array
    {
        $topic = Topic::find($topicId);
        $filename = $topic->storage_directory . $name . '.' . $ext;
        $dest = Storage::path($filename);
        $destDir = dirname($dest);        
        $mockPath = realpath(__DIR__.'/../../../mocks');
        Storage::putFileAs($topic->storage_directory, new File($mockPath . '/1.' . $ext), $name . '.' . $ext);

        return [
            'value' => $filename,
        ];
    }
}
