<?php

namespace Ulams\CourseBuilder\Apply;

use Throwable;
use Ulams\Courses\Models\Topic;
use Ulams\H5P\Services\Contracts\H5PServiceClientContract;
use Ulams\LiaScript\Models\LiaScriptDocument;
use Ulams\LiaScript\Models\LiaScriptTopic;
use Ulams\LiaScript\Services\Contracts\LiaScriptServiceContract;
use Ulams\TopicTypes\Models\TopicContent\H5P;

/**
 * Creates the documents and contents some topic types point to, through the packages' own services
 * (LiaScript documents with their versions, H5P contents through the H5P service), and turns the
 * blueprint's topic data into the fields the topic repository takes. The applier calls it once per
 * topic it creates or updates, and `release()` when it removes one.
 */
final class TopicWriter
{
    /**
     * @param array<string,mixed> $data the topic data of the blueprint (see ContentType::topics)
     * @return array<string,mixed> the fields for the topic repository, without `class`
     */
    public function prepare(string $class, array $data, ?int $existingTopicId, int $authorId): array
    {
        unset($data['class']);
        if ($class === LiaScriptTopic::class) {
            $markdown = (string) $data['markdown'];
            unset($data['markdown']);
            $service = app(LiaScriptServiceContract::class);
            $existing = $existingTopicId !== null ? $this->liascript($existingTopicId) : null;
            if ($existing !== null) {
                $document = $service->update($existing, (string) $data['title'], $markdown, null, 'Updated by the Course Builder', $authorId);
            } else {
                $document = $service->create((string) $data['title'], $markdown, null, $authorId);
            }

            return $data + ['value' => (int) $document->getKey()];
        }
        if ($class === H5P::class) {
            $client = app(H5PServiceClientContract::class);
            [$library, $params, $metadata] = [(string) $data['library'], (array) $data['h5pParams'], (array) $data['h5pMetadata']];
            unset($data['library'], $data['h5pParams'], $data['h5pMetadata']);
            $contentId = $existingTopicId !== null ? $this->h5pContentId($existingTopicId) : null;
            if ($contentId !== null) {
                $client->update($contentId, $library, $params, $metadata);
            } else {
                $contentId = $client->create($library, $params, $metadata);
            }

            return $data + ['value' => $contentId];
        }

        return $data;
    }

    /**
     * What a topic points to outside the topics tables (a LiaScript document, an H5P content), to be
     * deleted with `purge()` after the topic itself is gone (the topic row references it).
     *
     * @return array{liascript?:int,h5p?:int}
     */
    public function external(int $topicId): array
    {
        if ($document = $this->liascript($topicId)) {
            return ['liascript' => (int) $document->getKey()];
        }
        $contentId = $this->h5pContentId($topicId);

        return $contentId !== null ? ['h5p' => $contentId] : [];
    }

    /** Deletes what `external()` returned. Never throws: a leftover is harmless. */
    public function purge(array $external): void
    {
        try {
            if (isset($external['liascript']) && ($document = LiaScriptDocument::query()->find($external['liascript']))) {
                app(LiaScriptServiceContract::class)->delete($document);
            } elseif (isset($external['h5p'])) {
                app(H5PServiceClientContract::class)->delete($external['h5p']);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** The class of the topic's content, or null when the topic does not exist. */
    public function classOf(int $topicId): ?string
    {
        $type = Topic::query()->whereKey($topicId)->value('topicable_type');

        return is_string($type) ? $type : null;
    }

    private function liascript(int $topicId): ?LiaScriptDocument
    {
        $topic = Topic::query()->find($topicId);
        if ($topic === null || $topic->topicable_type !== LiaScriptTopic::class) {
            return null;
        }

        return LiaScriptDocument::query()->find((int) $topic->topicable?->value);
    }

    private function h5pContentId(int $topicId): ?int
    {
        $topic = Topic::query()->find($topicId);
        if ($topic === null || $topic->topicable_type !== H5P::class) {
            return null;
        }

        return $topic->topicable?->value !== null ? (int) $topic->topicable->value : null;
    }
}
