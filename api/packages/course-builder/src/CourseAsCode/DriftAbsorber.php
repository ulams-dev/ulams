<?php

namespace Ulams\CourseBuilder\CourseAsCode;

use Illuminate\Support\Carbon;
use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Apply\GiftRenderer;
use Ulams\CourseBuilder\Apply\LessonMarkdown;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\BlueprintDiff;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeGift\Models\GiftQuestion;
use Ulams\TopicTypes\Models\TopicContent\RichText;

/**
 * Reads admin edits made after an apply back into the blueprint (ADR 0160, plan 7.4). An element counts when its
 * LMS entity was changed after the last apply and its mapped fields differ from what the apply wrote. The edit is
 * absorbed into a proposed `author` version the author approves like any push: never into the current version, never
 * silently. A field is absorbed only where the blueprint still holds what was applied; where the blueprint changed
 * too, or the edit cannot be expressed in a blueprint (HTML from the visual editor, added or merged paragraphs,
 * changed correct answers), it is listed as unmapped and the blueprint value stays.
 *
 * Mapped back: course title, subtitle and description; module and lesson titles and summaries; the Markdown of a
 * RichText lesson (block by block); question stems, option texts, and the explanation. Everything else that was edited
 * (quiz topics, interactive activities, the landing page) is reported as unmapped.
 */
final class DriftAbsorber
{
    public function __construct(
        private readonly BlueprintApplier $applier,
        private readonly VersionService $versions,
        private readonly SchemaRegistry $schemas,
        private readonly EventLog $events,
    ) {
    }

    /**
     * What an absorption would change in `$doc`.
     *
     * @param array<string,mixed> $doc
     * @return array{document:array<string,mixed>,absorbed:array<int,array{key:string,element:string,title:string,fields:string[]}>,unmapped:array<int,array{key:string,element:string,title:string,reason:string}>}
     */
    public function scan(Session $session, array $doc): array
    {
        $result = ['document' => $doc, 'absorbed' => [], 'unmapped' => []];
        if ($session->course_id === null) {
            return $result;
        }
        $brief = (array) $session->brief;
        $current = $this->applier->desired($doc, $brief);
        $appliedDocs = [];
        $entries = EntityMapEntry::query()->where('session_id', $session->id)->whereNull('retired_at')
            ->whereIn('entity_type', ['course', 'lesson', 'topic', 'quiz_topic', 'gift_question', 'page'])->get();
        $entities = [];
        foreach (['course' => Course::class, 'lesson' => Lesson::class, 'topic' => Topic::class, 'quiz_topic' => Topic::class, 'gift_question' => GiftQuestion::class, 'page' => \Ulams\Pages\Models\Page::class] as $type => $model) {
            $ids = $entries->where('entity_type', $type)->pluck('entity_id')->all();
            $entities[$type] = $ids === [] ? collect() : $model::query()->whereIn('id', $ids)->get()->keyBy('id');
        }

        foreach ($entries as $entry) {
            $entity = $entities[$entry->entity_type][$entry->entity_id] ?? null;
            if ($entity === null || $entry->updated_at === null || $entity->updated_at === null || !Carbon::parse($entity->updated_at)->gt($entry->updated_at)) {
                continue;
            }
            $key = "{$entry->entity_type}:{$entry->element_id}";
            if (!isset($current[$key])) {
                continue; // the current blueprint no longer has the element: nothing to read back into
            }
            $applied = $entry->applied_version_id ? ($appliedDocs[$entry->applied_version_id] ??= Version::query()->find($entry->applied_version_id)?->document) : null;
            $was = $applied !== null ? ($this->applier->desired($applied, $brief)[$key]['data'] ?? null) : null;
            $now = $current[$key]['data'];
            if ($was === null) {
                $was = $now;
            }
            $this->read($result, $entry->entity_type, $entry->element_id, $key, $entity, $was, $now);
        }

        return $result;
    }

    /**
     * Stores the absorbed edits as a proposed `author` version on top of the current one.
     *
     * @param array{userId:int,tokenId?:?string,tokenKind?:?string,client?:?string,commit?:?string,changeUrl?:?string} $actor
     * @return array<string,mixed>
     */
    public function absorb(Session $session, array $actor, bool $dryRun = false): array
    {
        $current = $session->currentVersion;
        if ($current === null || !in_array($current->kind, VersionService::CONTENT_KINDS, true)) {
            throw new CourseAsCodeException('This course has no content blueprint to absorb edits into yet.', 409, 'NO_BLUEPRINT');
        }
        if ($session->course_id === null || $session->applied_version_id === null) {
            throw new CourseAsCodeException('The course has not been applied yet, so there are no edits made in the LMS to read back.', 409, 'NOT_APPLIED');
        }
        $document = Blueprint::upgrade($current->document);
        $scan = $this->scan($session, $document);
        $base = ['unchanged' => true, 'version' => null, 'counts' => ['added' => 0, 'changed' => 0, 'removed' => 0], 'changes' => [], 'absorbed' => $scan['absorbed'], 'unmapped' => $scan['unmapped'], 'dryRun' => $dryRun];
        if ($scan['absorbed'] === []) {
            return $base;
        }
        $changes = BlueprintDiff::compare($document, $scan['document']);
        if ($changes === []) {
            return $base;
        }
        $errors = $this->schemas->validateBlueprint($scan['document']);
        if ($errors === []) {
            $known = array_fill_keys($session->fragmentIds(), true);
            $errors = array_values(array_filter(Checks::blueprint($scan['document'], $known), fn ($e) => !str_starts_with($e, 'warning: ')));
        }
        if ($errors !== []) {
            throw new CourseAsCodeException('The edits made in the LMS do not make a valid blueprint.', 422, 'INVALID_BLUEPRINT', ['errors' => array_slice($errors, 0, 50)]);
        }
        $response = ['unchanged' => false, 'counts' => BlueprintDiff::counts($changes), 'changes' => array_slice($changes, 0, 200)] + $base;
        if ($dryRun) {
            return $response;
        }
        $version = $this->versions->create($session, $scan['document'], 'author', 'author', Version::PROPOSED, $current, "Admin edits since version " . (Version::query()->find($session->applied_version_id)?->number ?? '?'));
        $this->events->custom($session, null, 'course_as_code.drift_absorbed', array_filter([
            'versionId' => $version->id,
            'number' => $version->number,
            'actorId' => $actor['userId'],
            'tokenId' => $actor['tokenId'] ?? null,
            'tokenKind' => $actor['tokenKind'] ?? null,
            'client' => $actor['client'] ?? null,
            'elements' => array_column($scan['absorbed'], 'element'),
        ], fn ($v) => $v !== null && $v !== []));

        return ['version' => ['id' => $version->id, 'number' => $version->number, 'kind' => $version->kind, 'status' => $version->status, 'parentId' => $version->parent_id]] + $response;
    }

    /** @param array{document:array,absorbed:array,unmapped:array} $result */
    private function read(array &$result, string $type, string $element, string $key, mixed $entity, array $was, array $now): void
    {
        $doc = &$result['document'];
        $title = (string) ($now['title'] ?? $was['title'] ?? $type);
        $fields = [];
        $unmapped = function (string $reason) use (&$result, $key, $element, $title): void {
            $result['unmapped'][] = ['key' => $key, 'element' => $element, 'title' => $title, 'reason' => $reason];
        };
        /** a text field: absorbed when the admin changed it and the blueprint still holds the applied value */
        $text = function (string $dataKey, string $docKey, string $label) use (&$doc, $entity, $was, $now, $element, &$fields, $unmapped): void {
            $entityValue = (string) ($entity->{$dataKey} ?? '');
            $applied = (string) ($was[$dataKey] ?? '');
            if ($entityValue === $applied || $entityValue === (string) ($now[$dataKey] ?? '')) {
                return;
            }
            if ($applied !== (string) ($now[$dataKey] ?? '')) {
                $unmapped("{$label} was changed in the LMS and in the blueprint since the last apply; resolve it in the studio.");

                return;
            }
            $node = &self::find($doc, $element);
            if ($node === null) {
                return;
            }
            $node[$docKey] = $entityValue;
            $fields[] = $docKey;
        };

        switch ($type) {
            case 'course':
                $text('title', 'title', 'The course title');
                $text('subtitle', 'subtitle', 'The subtitle');
                $text('description', 'description', 'The description');
                break;
            case 'lesson':
                if (($doc['finalTest']['id'] ?? null) === $element) {
                    if ((string) $entity->title !== (string) ($was['title'] ?? '')) {
                        $unmapped('The final test lesson is generated; its title cannot be changed through the blueprint.');
                    }
                    break;
                }
                $text('title', 'title', 'The module title');
                $text('summary', 'summary', 'The module summary');
                break;
            case 'topic':
                $text('title', 'title', 'The lesson title');
                $text('summary', 'summary', 'The lesson summary');
                if ($entity->topicable_type === RichText::class) {
                    $this->readLesson($doc, $element, (string) ($entity->topicable?->value ?? ''), (string) ($was['value'] ?? ''), (string) ($now['value'] ?? ''), $fields, $unmapped);
                }
                break;
            case 'gift_question':
                $this->readQuestion($doc, $element, (string) $entity->value, (string) ($was['value'] ?? ''), (string) ($now['value'] ?? ''), $fields, $unmapped);
                break;
            case 'quiz_topic':
                if ((string) $entity->title !== (string) ($was['title'] ?? '')) {
                    $unmapped('The quiz title was edited in the LMS; it is generated from the lesson and cannot be changed through the blueprint.');
                }
                break;
            case 'page':
                if ((string) $entity->content !== (string) ($was['content'] ?? '')) {
                    $unmapped('The landing page was edited in the LMS; copy the changes into the landing document by hand.');
                }
                break;
        }
        if ($fields !== []) {
            $result['absorbed'][] = ['key' => $key, 'element' => $element, 'title' => $title, 'fields' => array_values(array_unique($fields))];
        }
    }

    private function readLesson(array &$doc, string $element, string $entityMarkdown, string $applied, string $current, array &$fields, \Closure $unmapped): void
    {
        $text = rtrim($entityMarkdown);
        if ($text === rtrim($applied) || $text === rtrim($current)) {
            return;
        }
        if (rtrim($applied) !== rtrim($current)) {
            $unmapped('The lesson text was changed in the LMS and in the blueprint since the last apply; resolve it in the studio.');

            return;
        }
        $lesson = &self::find($doc, $element);
        if ($lesson === null || !isset($lesson['blocks'])) {
            return;
        }
        $body = (string) preg_replace('/\n*---\n\n\*\*Sources\*\*\n\n.*\z/s', '', $text);
        $rendered = LessonMarkdown::blocks($lesson);
        if (rtrim($body) === implode("\n\n", $rendered)) {
            return; // only the sources list changed, and that is generated
        }
        if (Checks::markup($body, 'x') !== []) {
            $unmapped('The lesson text now holds HTML or a remote image (the visual editor does that); a blueprint block holds Markdown only. Copy the wording into the blocks by hand.');

            return;
        }
        $chunks = self::chunks($body);
        $sizes = array_map(fn (string $r) => count(self::chunks($r)), $rendered);
        if (count($chunks) !== array_sum($sizes)) {
            $unmapped('Paragraphs were added, removed or merged in the LMS; copy the new text into the blocks by hand.');

            return;
        }
        $offset = 0;
        $changed = false;
        foreach ($lesson['blocks'] as $i => &$block) {
            $new = implode("\n\n", array_slice($chunks, $offset, $sizes[$i]));
            $offset += $sizes[$i];
            if ($new === $rendered[$i]) {
                continue;
            }
            if ($block['kind'] === 'callout') {
                $lines = explode("\n", $new);
                if (array_filter($lines, fn ($l) => !str_starts_with($l, '>')) !== []) {
                    $unmapped('A callout lost its quote marks in the LMS; copy the wording into the block by hand.');

                    return;
                }
                $new = implode("\n", array_map(fn ($l) => (string) preg_replace('/^> ?/', '', $l), $lines));
            }
            $block['markdown'] = $new;
            $block['origin'] = 'author';
            $changed = true;
        }
        unset($block);
        if ($changed) {
            $fields[] = 'blocks';
        }
    }

    private function readQuestion(array &$doc, string $element, string $entityGift, string $applied, string $current, array &$fields, \Closure $unmapped): void
    {
        if ($entityGift === $applied || $entityGift === $current) {
            return;
        }
        if ($applied !== $current) {
            $unmapped('The question was changed in the LMS and in the blueprint since the last apply; resolve it in the studio.');

            return;
        }
        $question = &self::find($doc, $element);
        $read = GiftReader::read($entityGift);
        if ($question === null || !isset($question['options'])) {
            return;
        }
        if ($read === null) {
            $unmapped('The question was edited in a form the blueprint cannot read back; edit it in the blueprint instead.');

            return;
        }
        $options = $question['options'];
        if ($read['truth'] !== null) {
            $correct = array_values(array_filter($options, fn ($o) => $o['correct']))[0]['text'] ?? '';
            if ((strtolower(trim((string) $correct)) === 'true') !== $read['truth']) {
                $unmapped('The correct answer of a true/false question was changed in the LMS; change it in the blueprint instead.');

                return;
            }
        } else {
            if (count($read['answers']) !== count($options)) {
                $unmapped('Answers were added or removed in the LMS; change the options in the blueprint instead.');

                return;
            }
            foreach ($read['answers'] as $i => $answer) {
                if ($answer['correct'] !== (bool) $options[$i]['correct']) {
                    $unmapped('Which answer is correct was changed in the LMS; change it in the blueprint instead.');

                    return;
                }
            }
        }
        $changed = false;
        if ($read['stem'] !== GiftRenderer::plain((string) $question['stem'])) {
            $question['stem'] = $read['stem'];
            $changed = true;
        }
        if ($read['truth'] === null) {
            foreach ($read['answers'] as $i => $answer) {
                if ($answer['text'] !== GiftRenderer::plain((string) $options[$i]['text'])) {
                    $question['options'][$i]['text'] = $answer['text'];
                    $changed = true;
                }
            }
            if ($question['type'] === 'single') {
                $why = array_values(array_filter($read['answers'], fn ($a) => $a['correct']))[0]['feedback'] ?? null;
                if ($why !== null && $why !== GiftRenderer::plain((string) ($question['explanation'] ?? ''))) {
                    $question['explanation'] = $why;
                    $changed = true;
                }
            }
        }
        if ($changed) {
            $question['origin'] = 'author';
            $fields[] = 'question';
        }
    }

    /** Blank-line separated chunks of Markdown; blank lines inside code fences do not split. @return string[] */
    private static function chunks(string $markdown): array
    {
        $chunks = [];
        $current = [];
        $fence = null;
        foreach (explode("\n", trim($markdown)) as $line) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $m)) {
                $fence = $fence === null ? $m[1][0] : ($fence === $m[1][0] ? null : $fence);
            }
            if ($fence === null && trim($line) === '') {
                if ($current !== []) {
                    $chunks[] = implode("\n", $current);
                    $current = [];
                }
                continue;
            }
            $current[] = $line;
        }
        if ($current !== []) {
            $chunks[] = implode("\n", $current);
        }

        return $chunks;
    }

    /** A reference to the element with this id anywhere in the document, or a reference to null. */
    private static function &find(array &$node, string $id): ?array
    {
        if (($node['id'] ?? null) === $id) {
            return $node;
        }
        foreach ($node as $key => &$child) {
            if ($key === 'data' || !is_array($child)) {
                continue;
            }
            $found = &self::find($child, $id);
            if ($found !== null) {
                return $found;
            }
        }
        unset($child);
        $none = null;

        return $none;
    }
}
