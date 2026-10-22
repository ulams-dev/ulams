<?php

namespace Ulams\CourseBuilder\ContentTypes;

use Ulams\Courses\Models\Topic;

/**
 * A lesson that stands for a topic that already exists in the LMS (`assetRef: {entityType: "topic", entityId}`, set when a hand-made
 * course is adopted): the apply keeps the topic and only follows the lesson's title, module and position. Its content (an H5P
 * activity, a LiaScript document, an embedded page) is not rewritten from the blueprint.
 */
final class ReferencedTopics
{
    public static function entityId(array $lesson): ?int
    {
        $ref = $lesson['assetRef'] ?? null;

        return is_array($ref) && ($ref['entityType'] ?? null) === 'topic' && isset($ref['entityId']) && ctype_digit((string) $ref['entityId']) ? (int) $ref['entityId'] : null;
    }

    /** @return array<int,array{slot:string,element:string,class:string,data:array<string,mixed>}>|null null when the lesson is not a reference */
    public static function topics(array $lesson): ?array
    {
        $id = self::entityId($lesson);
        if ($id === null) {
            return null;
        }
        $class = Topic::query()->whereKey($id)->value('topicable_type');

        return [['slot' => 'primary', 'element' => $lesson['id'], 'class' => is_string($class) ? $class : 'missing', 'data' => ['title' => $lesson['title'], 'referenced' => true]]];
    }
}
