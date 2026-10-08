<?php

namespace EscolaLms\TopicTypes\Models\TopicContent;

use EscolaLms\TopicTypes\Events\TopicTypeChanged;
use EscolaLms\TopicTypes\Models\Contracts\TopicContentContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use EscolaLms\Courses\Models\TopicContent\AbstractTopicContent as AbstractTopicContentExtend;

/**
 * @property mixed $value
 */
abstract class AbstractTopicContent extends AbstractTopicContentExtend implements TopicContentContract
{
    protected static function booted()
    {
        $user = Auth::user();
        static::saved(function (AbstractTopicContent $topicContent) use ($user) {
            if ($user  && ($topicContent->wasRecentlyCreated || $topicContent->wasChanged('value'))) {
                event(new TopicTypeChanged($user, $topicContent));
            }
        });
    }

    public function fixAssetPaths(): array
    {
        $topic = $this->topic;
        $course = $topic->lesson->course;
        $basename = basename($this->value);
        $destination = sprintf('course/%d/topic/%d/%s/%s', $course->id, $topic->id, $this->getStoragePathFinalSegment(), $basename);
        $results = [];
        if (strpos($this->value, $destination) === false && Storage::exists($this->value)) {
            if (!Storage::exists($destination)) {
                Storage::move($this->value, $destination);
            }
            $results[] = [$this->value, $destination];
            $this->value = $destination;
            $this->save();
        }

        return $results;
    }

    public function getStoragePathFinalSegment(): string
    {
        return '';
    }

    public function processMetadataInfo(): void
    {
        // do sth
    }
}
