<?php

namespace Ulams\CourseBuilder\Services;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;

/**
 * Read-only view of the entity map for other packages (learner-insights): which blueprint element
 * an LMS entity was built from, and how to name it. Never writes; the applier owns the map.
 */
class ElementLookup
{
    /** @var array<string,array{session_id:string,element_id:string}|null> */
    private array $reverse = [];

    /** @var array<string,array<string,string>> */
    private array $labels = [];

    /** @return array{session_id:string,element_id:string}|null */
    public function forEntity(string $entityType, int $entityId): ?array
    {
        $key = $entityType . ':' . $entityId;
        if (!array_key_exists($key, $this->reverse)) {
            $entry = EntityMapEntry::query()->where('entity_type', $entityType)->where('entity_id', $entityId)->whereNull('retired_at')->first();
            $this->reverse[$key] = $entry ? ['session_id' => $entry->session_id, 'element_id' => $entry->element_id] : null;
        }

        return $this->reverse[$key];
    }

    /** @return array{session_id:string,element_id:string}|null */
    public function forTopic(int $topicId): ?array
    {
        return $this->forEntity('topic', $topicId) ?? $this->forEntity('quiz_topic', $topicId);
    }

    /** @return array{session_id:string,element_id:string}|null */
    public function forGiftQuestion(int $questionId): ?array
    {
        return $this->forEntity('gift_question', $questionId);
    }

    /** Human label of an element in the applied version (for example "Lesson 2.3"); null when unknown. */
    public function label(string $sessionId, string $elementId): ?string
    {
        if (!isset($this->labels[$sessionId])) {
            $this->labels[$sessionId] = [];
        }
        if (!array_key_exists($elementId, $this->labels[$sessionId])) {
            $session = Session::query()->find($sessionId);
            $version = $session?->applied_version_id ? Version::query()->find($session->applied_version_id) : null;
            $found = $version ? Blueprint::find($version->document, $elementId) : null;
            $title = $found['node']['title'] ?? null;
            $this->labels[$sessionId][$elementId] = $found ? trim($found['label'] . ($title ? ' ' . $title : '')) : '';
        }

        return $this->labels[$sessionId][$elementId] ?: null;
    }
}
