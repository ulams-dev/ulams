<?php

namespace Ulams\CourseBuilder\Pipeline;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Events\ElementPatched;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Ui\Surfaces;
use Ulams\CourseBuilder\Ui\UiCatalogue;

/**
 * Element-scoped chat (spec 2.5): the selected element, its parent summary, the brief, its cited
 * fragments and the (cached) source go to the model, which returns a replacement subtree validated
 * against that element type. The server preserves ids, computes the diff and proposes a version;
 * nothing changes until the author approves.
 */
final class PatchService
{
    public function __construct(
        private readonly Llm $llm,
        private readonly PromptContext $context,
        private readonly VersionService $versions,
        private readonly Surfaces $surfaces,
        private readonly UiCatalogue $catalogue,
        private readonly EventLog $events,
    ) {
    }

    public function propose(Session $session, Run $run, string $elementId, string $message): Version
    {
        $made = $this->generate($session, $run, $elementId, $message);
        $this->events->text($session, $run, (string) $made['reply']);
        $this->surfaces->patch($session, $run, $made['version'], $elementId, $made['label'], $message, 'proposed', $this->preview((string) $made['ui'], $made['type'], $made['replacement']));

        return $made['version'];
    }

    /**
     * "Give me options": `count` proposals for one element, made together and compared side by side.
     * Each is a normal proposed patch version; they share `variant_group`. One model call each, so the
     * cost is count × a chat edit.
     *
     * @return Version[]
     */
    public function variants(Session $session, Run $run, string $elementId, string $message, int $count): array
    {
        $count = max(2, min(3, $count));
        $group = Blueprint::newId();
        $made = [];
        for ($i = 1; $i <= $count; $i++) {
            $made[] = $this->generate($session, $run, $elementId, $message . "\n(Option {$i} of {$count}: take a clearly different approach from the other options.)", $group);
        }
        $this->events->text($session, $run, sprintf('Here are %d options for %s. Compare them and choose one; nothing changes until you do.', $count, $made[0]['label']));
        $this->surfaces->variants($session, $run, $group, $elementId, $made[0]['label'], $message, array_map(fn ($m) => ['version' => $m['version'], 'reply' => (string) $m['reply']], $made), 'proposed');

        return array_column($made, 'version');
    }

    /**
     * @return array{version:Version,reply:string,ui:string,type:string,label:string,replacement:array}
     */
    private function generate(Session $session, Run $run, string $elementId, string $message, ?string $group = null): array
    {
        $current = $session->currentVersion;
        if ($current === null || !in_array($current->kind, VersionService::CONTENT_KINDS, true)) {
            throw new InvalidArgumentException('Chat edits are available once the lessons are generated.');
        }
        $doc = $current->document;
        $found = Blueprint::find($doc, $elementId);
        if ($found === null) {
            throw new InvalidArgumentException('That element is not part of the current version.');
        }
        $type = $found['type'];
        $schema = PatchSchemas::for($type);
        $known = $this->context->knownFragments($session);
        $element = self::editable($type, $found['node']);
        $parent = $this->parentSummary($doc, $found);
        $allowedObjectives = $this->objectiveIds($doc, $found);
        $map = $this->context->fragmentMap($session);
        $cited = array_values(array_filter(array_map(fn ($id) => isset($map[$id]) ? ['id' => $id, 'section' => $map[$id]->label()] : null, Blueprint::citations($found['node']))));

        $result = $this->llm->generate($session, $run, 'patch', [
            $this->context->sourceBlock($session),
            $this->context->contextBlock($session, $doc),
            $this->context->instruction(
                "<author_request>" . PromptContext::esc($message) . "</author_request>\nRevise the element in task_input as the author asks.",
                ['element' => ['type' => $type, 'label' => $found['label']] + $element, 'parent' => $parent, 'citedFragments' => $cited],
            ),
        ], fn (array $data) => self::validate($type, $data['replacement'] ?? [], $known, $allowedObjectives), $schema);

        $replacement = self::merge($type, $found['node'], $result->data['replacement']);
        $newDoc = Blueprint::setAt($doc, $found['path'], $replacement);
        $version = $this->versions->create($session, $newDoc, 'patch', 'ai', Version::PROPOSED, $current, $message, $elementId, $result->callIds);
        if ($group !== null) {
            $version->forceFill(['variant_group' => $group])->save();
        }

        return ['version' => $version, 'reply' => (string) $result->data['reply'], 'ui' => (string) $result->data['ui'], 'type' => $type, 'label' => $found['label'], 'replacement' => $replacement];
    }

    /** Approves one option and rejects the others of its group, in one transaction. */
    public function chooseVariant(Session $session, ?Run $run, Version $version, int $userId): void
    {
        $group = (string) $version->variant_group;
        $siblings = $group === '' ? collect() : Version::query()->where('session_id', $session->id)->where('variant_group', $group)->where('id', '!=', $version->id)->where('status', Version::PROPOSED)->get();
        DB::transaction(function () use ($siblings, $session, $version, $userId) {
            foreach ($siblings as $other) {
                $this->versions->reject($other, $userId);
            }
            $this->versions->approve($version, $userId);
            $this->versions->setCurrent($session, $version);
        });
        event(new ElementPatched($session, (string) $version->element_id));
        if ($group !== '') {
            $this->surfaces->close($session, "variants-{$group}");
            $this->surfaces->variants($session, $run, $group, (string) $version->element_id, (string) (Blueprint::find($version->document, (string) $version->element_id)['label'] ?? 'Element'), (string) $version->reason, array_map(fn (Version $v) => ['version' => $v->refresh(), 'reply' => ''], [$version, ...$siblings->all()]), 'chosen');
        }
    }

    /** Rejects every open option of a group. */
    public function rejectVariants(Session $session, ?Run $run, string $group, int $userId): void
    {
        $open = Version::query()->where('session_id', $session->id)->where('variant_group', $group)->where('status', Version::PROPOSED)->get();
        foreach ($open as $v) {
            $this->versions->reject($v, $userId);
        }
        if ($open->isNotEmpty()) {
            $first = $open->first();
            $this->surfaces->variants($session, $run, $group, (string) $first->element_id, (string) (Blueprint::find($first->document, (string) $first->element_id)['label'] ?? 'Element'), (string) $first->reason, array_map(fn (Version $v) => ['version' => $v->refresh(), 'reply' => ''], $open->all()), 'rejected');
        }
    }

    /** The fields of an element the model may change (no generated ids of the element itself). */
    public static function editable(string $type, array $node): array
    {
        return match ($type) {
            'course' => ['title' => $node['title'], 'subtitle' => $node['subtitle'] ?? '', 'description' => $node['description'] ?? ''],
            'module' => ['title' => $node['title'], 'summary' => $node['summary'] ?? ''],
            'lesson' => ['title' => $node['title'], 'summary' => $node['summary'] ?? '', 'minutes' => $node['minutes'], 'objectives' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $o['text']], $node['objectives']), 'blocks' => $node['blocks']],
            'block', 'question' => $node,
        };
    }

    /** @return string[] */
    public static function validate(string $type, array $r, array $known, array $objectiveIds): array
    {
        $errors = [];
        $blocks = match ($type) {
            'lesson' => $r['blocks'] ?? [],
            'block' => [$r],
            default => [],
        };
        foreach ($blocks as $i => $block) {
            $errors = [...$errors, ...Checks::citations($block['citations'] ?? [], $known, "blocks/{$i}"), ...Checks::markup((string) ($block['markdown'] ?? ''), "blocks/{$i}")];
            foreach ($block['objectiveIds'] ?? [] as $oid) {
                if (!in_array($oid, $objectiveIds, true)) {
                    $errors[] = "blocks/{$i}: unknown objective {$oid}";
                }
            }
        }
        if ($type === 'question') {
            $errors = [...$errors, ...Checks::citations($r['citations'] ?? [], $known, 'question'), ...Checks::questionShape($r, 'question')];
            foreach (['stem', 'explanation'] as $f) {
                $errors = [...$errors, ...Checks::markup((string) ($r[$f] ?? ''), $f)];
            }
            foreach ($r['objectiveIds'] ?? [] as $oid) {
                if (!in_array($oid, $objectiveIds, true)) {
                    $errors[] = "question: unknown objective {$oid}";
                }
            }
        }
        foreach (['title', 'subtitle', 'summary', 'description'] as $f) {
            if (isset($r[$f])) {
                $errors = [...$errors, ...Checks::markup((string) $r[$f], $f)];
            }
        }

        return $errors;
    }

    /** Replacement → element: own id forced, known child ids kept, new children get server ids. */
    public static function merge(string $type, array $old, array $r): array
    {
        $keep = fn (array $items, array $oldItems) => array_map(function ($item) use ($oldItems) {
            $known = array_column($oldItems, 'id');
            $item['id'] = isset($item['id']) && in_array($item['id'], $known, true) ? $item['id'] : Blueprint::newId();

            return $item;
        }, $items);
        $clean = fn (array $b) => ['id' => $b['id'], 'kind' => $b['kind'], 'markdown' => trim($b['markdown']), 'citations' => array_values(array_unique($b['citations'])), 'objectiveIds' => array_values(array_unique($b['objectiveIds']))];

        return match ($type) {
            'course' => array_merge($old, array_filter(['title' => trim($r['title']), 'subtitle' => trim($r['subtitle']) ?: null, 'description' => trim($r['description']) ?: null], fn ($v) => $v !== null)),
            'module' => array_merge($old, ['title' => trim($r['title'])] + (trim($r['summary']) !== '' ? ['summary' => trim($r['summary'])] : [])),
            'lesson' => array_merge($old, ['title' => trim($r['title']), 'minutes' => (int) $r['minutes'], 'blocks' => array_map($clean, $keep($r['blocks'], $old['blocks'] ?? [])), 'flags' => []]
                + (trim($r['summary']) !== '' ? ['summary' => trim($r['summary'])] : [])),
            'block' => $clean(['id' => $old['id']] + $r),
            'question' => [
                'id' => $old['id'],
                'type' => $r['type'],
                'stem' => trim($r['stem']),
                'options' => array_map(fn ($o) => ['id' => $o['id'], 'text' => trim($o['text']), 'correct' => (bool) $o['correct']], $keep($r['options'], $old['options'] ?? [])),
                'explanation' => trim($r['explanation']),
                'citations' => array_values(array_unique($r['citations'])),
                'objectiveIds' => array_values(array_unique($r['objectiveIds'])),
            ],
        };
    }

    private function parentSummary(array $doc, array $found): array
    {
        if ($found['lessonId'] !== null) {
            $lesson = Blueprint::find($doc, $found['lessonId']);
            if ($lesson !== null && $found['type'] !== 'lesson') {
                return ['type' => 'lesson', 'title' => $lesson['node']['title'], 'objectives' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $o['text']], $lesson['node']['objectives'])];
            }
        }

        return ['type' => 'course', 'title' => $doc['course']['title']];
    }

    /** @return string[] objective ids the element may refer to */
    private function objectiveIds(array $doc, array $found): array
    {
        if ($found['lessonId'] !== null) {
            return array_column(Blueprint::find($doc, $found['lessonId'])['node']['objectives'] ?? [], 'id');
        }
        $ids = [];
        foreach (Blueprint::lessons($doc) as $item) {
            $ids = [...$ids, ...array_column($item['lesson']['objectives'], 'id')];
        }

        return $ids;
    }

    /** The preview card the model asked for, validated against the catalogue (null = DiffView only). */
    private function preview(string $ui, string $type, array $node): ?array
    {
        $labels = Surfaces::labels(['x' => Blueprint::citations($node)]);
        $cite = fn (array $ids) => array_map(fn ($id) => ['fragmentId' => $id, 'label' => $labels[$id] ?? $id], array_slice($ids, 0, 10));
        if ($ui === 'QuizQuestionCard' && $type === 'question') {
            return $this->catalogue->nodeOrFallback('preview', 'QuizQuestionCard', [
                'questionId' => $node['id'], 'type' => $node['type'], 'stem' => $node['stem'],
                'options' => array_map(fn ($o) => ['id' => $o['id'], 'text' => mb_substr($o['text'], 0, 500), 'correct' => $o['correct']], $node['options']),
                'explanation' => mb_substr($node['explanation'], 0, 2000), 'citations' => $cite($node['citations']),
            ], $node['stem'], 'chat-reply');
        }
        if ($ui === 'LessonPreviewCard' && $type === 'lesson') {
            return $this->catalogue->nodeOrFallback('preview', 'LessonPreviewCard', [
                'lessonId' => $node['id'], 'title' => $node['title'], 'minutes' => (int) $node['minutes'],
                'blocks' => array_map(fn ($b) => ['id' => $b['id'], 'kind' => $b['kind'], 'markdown' => $b['markdown'], 'citations' => $cite($b['citations'])], $node['blocks']),
            ], $node['title'], 'chat-reply');
        }

        return null;
    }

    public function approve(Session $session, ?Run $run, Version $version, int $userId): void
    {
        $this->versions->approve($version, $userId);
        $this->versions->setCurrent($session, $version);
        event(new ElementPatched($session, (string) $version->element_id));
        $found = Blueprint::find($version->document, (string) $version->element_id);
        $this->surfaces->patch($session, $run, $version, (string) $version->element_id, $found['label'] ?? 'Element', (string) $version->reason, 'approved');
    }

    public function reject(Session $session, ?Run $run, Version $version, int $userId): void
    {
        $this->versions->reject($version, $userId);
        $found = Blueprint::find($version->document, (string) $version->element_id);
        $this->surfaces->patch($session, $run, $version, (string) $version->element_id, $found['label'] ?? 'Element', (string) $version->reason, 'rejected');
    }
}
