<?php

namespace Ulams\CourseBuilder\ContentTypes;

/**
 * The lesson content types of this installation. Unknown or disabled keys resolve to rich text, so
 * a blueprint written where LiaScript or H5P was available still applies (as text) where it is not.
 */
final class ContentTypeRegistry
{
    /** @var array<string,ContentType> */
    private array $types = [];

    public function __construct(RichTextType $richtext, LiaScriptType $liascript, H5pType $h5p, InteractiveType $interactive)
    {
        foreach ([$richtext, $liascript, $h5p, $interactive] as $type) {
            $this->types[$type->key()] = $type;
        }
    }

    /** @return string[] every known key, in display order */
    public function keys(): array
    {
        return array_keys($this->types);
    }

    /** @return array<string,ContentType> */
    public function all(): array
    {
        return $this->types;
    }

    /** @return array<string,ContentType> the types that can be created now */
    public function enabled(): array
    {
        return array_filter($this->types, fn (ContentType $t) => $t->enabled());
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /**
     * The type for a `contentType` key; rich text when the key is unknown. With `$requireEnabled` (the
     * generation pipeline) a type this installation cannot create also resolves to rich text. The
     * applier does not require it: a lesson that has its H5P activity keeps it while the service is
     * briefly unreachable, instead of the apply deleting the activity.
     */
    public function for(string $key, bool $requireEnabled = false): ContentType
    {
        $type = $this->types[$key] ?? $this->types['richtext'];

        return !$requireEnabled || $type->enabled() ? $type : $this->types['richtext'];
    }

    public function forLesson(array $lesson, bool $requireEnabled = false): ContentType
    {
        return $this->for((string) ($lesson['contentType'] ?? 'richtext'), $requireEnabled);
    }

    /**
     * What the author may choose for a lesson in the outline: the enabled types that suit it.
     * LiaScript needs at least two objectives to be worth its self-checks; H5P and interactive suit any lesson.
     *
     * @return string[]
     */
    public function suggest(array $lesson): array
    {
        $keys = ['richtext'];
        foreach ($this->enabled() as $key => $type) {
            if ($key === 'richtext') {
                continue;
            }
            if ($key === 'liascript' && count($lesson['objectives'] ?? []) < 2) {
                continue;
            }
            $keys[] = $key;
        }

        return $keys;
    }
}
