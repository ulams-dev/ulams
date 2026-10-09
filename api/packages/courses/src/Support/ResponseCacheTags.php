<?php

namespace Ulams\Courses\Support;

use Illuminate\Cache\TaggableStore;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * Targeted invalidation of the response cache (spatie/laravel-responsecache).
 *
 * Cached responses are tagged by what they show:
 *  - `catalogue`: course list, course detail, programs, topic resources;
 *  - `progress`: the per-user progress list.
 *
 * A write to a learner-activity model (progress, time tracking, quiz attempts, ...) clears only
 * `progress`, so the progress ping a lesson page sends every few seconds no longer empties the
 * catalogue. Writes to models that no cached response shows clear nothing. Any other `Ulams*`
 * model write clears both tags, as before.
 *
 * Tags need a taggable store (Redis, array). With a store that cannot tag (file), every clear
 * empties the whole response cache store, which is the previous behaviour.
 */
class ResponseCacheTags
{
    public const CATALOGUE = 'catalogue';
    public const PROGRESS = 'progress';

    /** Writes that only change what the progress endpoints show. */
    public const ACTIVITY_MODELS = [
        'Ulams\\Courses\\Models\\CourseProgress',
        'Ulams\\Courses\\Models\\UserTopicTime',
        'Ulams\\Courses\\Models\\H5PUserProgress',
        'Ulams\\Courses\\Models\\CourseUserAttendance',
        'Ulams\\TopicTypeGift\\Models\\QuizAttempt',
        'Ulams\\TopicTypeGift\\Models\\AttemptAnswer',
    ];

    /** Namespaces and classes whose writes no cached response shows. */
    public const IGNORED = [
        'Ulams\\Lrs\\',
        'Ulams\\BookmarksNotes\\',
        'Ulams\\Notifications\\',
        'Ulams\\Auth\\Models\\UserSetting',
        'Ulams\\Auth\\Models\\PreUser',
        'Ulams\\Auth\\Models\\SocialAccount',
        'Ulams\\Questionnaire\\Models\\QuestionAnswer',
        'Ulams\\Tasks\\Models\\TaskNote',
    ];

    /** @return list<string> tags to clear after a write to a model of this class */
    public static function tagsFor(string $class): array
    {
        $class = ltrim($class, '\\');
        if (in_array($class, self::ACTIVITY_MODELS, true)) {
            return [self::PROGRESS];
        }
        foreach (self::IGNORED as $ignored) {
            if ($class === $ignored || (str_ends_with($ignored, '\\') && str_starts_with($class, $ignored))) {
                return [];
            }
        }

        return [self::CATALOGUE, self::PROGRESS];
    }

    public static function clearFor(string $class): void
    {
        $tags = self::tagsFor($class);
        if ($tags !== []) {
            self::clear(...$tags);
        }
    }

    public static function clear(string ...$tags): void
    {
        $tags = $tags === [] ? [self::CATALOGUE, self::PROGRESS] : $tags;
        if (self::taggable()) {
            ResponseCache::clear($tags);

            return;
        }
        ResponseCache::clear();
    }

    /** Tags usable on routes: none when the store cannot tag. */
    public static function forRoute(array $tags): array
    {
        return self::taggable() ? array_values($tags) : [];
    }

    public static function taggable(): bool
    {
        return app('cache')->store(config('responsecache.cache.store'))->getStore() instanceof TaggableStore;
    }
}
