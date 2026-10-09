<?php

namespace Ulams\CourseBuilder\Ui;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\BlueprintDiff;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Pipeline\Llm;

/**
 * Builds A2UI surfaces from validated data (deterministic code, never the model) and streams them.
 * Every surface the server issues is recorded in the session state with its kind, so an action
 * coming back can be checked against the surface it claims to come from.
 */
final class Surfaces
{
    public function __construct(private readonly EventLog $events, private readonly UiCatalogue $catalogue)
    {
    }

    /** @param array<int,array<string,mixed>> $components */
    public function emit(Session $session, ?Run $run, string $surfaceId, string $kind, array $components, array $meta = []): void
    {
        foreach ($components as $node) {
            if (($node['component'] ?? '') === 'Column') {
                continue;
            }
            $props = $node;
            unset($props['id'], $props['component'], $props['children']);
            $errors = $this->catalogue->validate((string) $node['component'], $props);
            if ($errors !== []) {
                throw new \LogicException("Surface {$surfaceId}: invalid {$node['component']}: " . implode('; ', array_slice($errors, 0, 3)));
            }
        }
        $session->putState("surfaces.{$surfaceId}", ['kind' => $kind, 'open' => true] + $meta);
        $session->save();
        $this->events->surface($session, $run, $surfaceId, $this->catalogue->catalogId(), $components, [], ['kind' => $kind] + $meta);
    }

    public function close(Session $session, string $surfaceId): void
    {
        if ($session->stateValue("surfaces.{$surfaceId}") !== null) {
            $session->putState("surfaces.{$surfaceId}.open", false);
            $session->save();
        }
    }

    /** @return array<string,mixed>|null the issued surface, only while it is open */
    public function openSurface(Session $session, string $surfaceId): ?array
    {
        $surface = $session->stateValue("surfaces.{$surfaceId}");

        return is_array($surface) && !empty($surface['open']) ? $surface : null;
    }

    public function source(Session $session, ?Run $run, Source $source): void
    {
        $sections = [];
        $seen = [];
        foreach ($source->fragments()->get() as $f) {
            /** @var Fragment $f */
            $key = implode('/', $f->heading_path);
            if (isset($seen[$key]) || $f->heading_path === []) {
                continue;
            }
            $seen[$key] = true;
            $sections[] = ['fragmentId' => $f->id, 'label' => mb_substr($f->label(), 0, 160), 'level' => max(0, $f->level - 1)];
        }
        $props = array_filter([
            'sourceId' => $source->id,
            'name' => $source->original_name,
            'status' => $source->status,
            'sizeBytes' => $source->size,
            'pages' => $source->metadata['pages'] ?? null,
            'tokens' => $source->token_estimate,
            'fragmentCount' => (int) ($source->metadata['fragments'] ?? 0),
            'sections' => array_slice($sections, 0, 400),
            'error' => $source->error ? mb_substr($source->error, 0, 500) : null,
        ], fn ($v) => $v !== null);
        $this->emit($session, $run, "source-{$source->id}", 'source', [['id' => 'root', 'component' => 'SourceCard'] + $props]);
    }

    /** @param array<int,array<string,mixed>> $nodes interview control nodes (already validated) */
    public function interview(Session $session, ?Run $run, array $nodes, int $open): void
    {
        $children = array_column($nodes, 'id');
        $components = [['id' => 'root', 'component' => 'Column', 'gap' => 'md', 'children' => [...$children, 'decide']], ...$nodes];
        $components[] = ['id' => 'decide', 'component' => 'DecideForMe', 'label' => $open > 0 ? 'Decide the rest for me' : 'All answered', 'open' => $open];
        $this->emit($session, $run, 'interview', 'interview', $components);
    }

    public function outline(Session $session, ?Run $run, Version $version, ?array $previous = null, string $status = 'proposed', ?string $comment = null): void
    {
        $doc = $version->document;
        $fragments = self::labels($doc);
        $changes = $previous !== null ? BlueprintDiff::compare($previous, $doc) : [];
        $byId = [];
        foreach ($changes as $c) {
            $byId[$c['id']] = $c;
        }
        $mark = fn (string $id) => $previous === null ? 'unchanged' : (isset($byId[$id]) ? ($byId[$id]['kind'] === 'added' ? 'added' : 'changed') : 'unchanged');
        $cite = fn (array $ids) => array_values(array_map(fn ($id) => ['fragmentId' => $id, 'label' => $fragments[$id] ?? $id], array_slice($ids, 0, 20)));
        $objective = function (array $o) use ($cite, $mark, $byId) {
            $out = ['id' => $o['id'], 'text' => $o['text'], 'change' => $mark($o['id']), 'citations' => $cite($o['citations'] ?? [])];
            foreach ($byId[$o['id']]['fields'] ?? [] as $f) {
                if ($f['field'] === 'text' && is_string($f['before'])) {
                    $out['before'] = $f['before'];
                }
            }

            return $out;
        };
        $stats = Blueprint::stats($doc);
        $removed = [];
        foreach ($changes as $c) {
            if ($c['kind'] === 'removed' && in_array($c['type'], ['module', 'lesson'], true)) {
                $removed[] = ['title' => (string) ($byId[$c['id']]['fields'][0]['before'] ?? $c['label']), 'kind' => $c['type']];
            }
        }
        $props = array_filter([
            'versionId' => $version->id,
            'number' => $version->number,
            'status' => $status,
            'editable' => $status === 'proposed',
            'targetMinutes' => (int) ($session->brief['totalMinutes'] ?? 0),
            'summary' => ['modules' => $stats['modules'], 'lessons' => $stats['lessons'], 'minutes' => $stats['minutes'], 'objectives' => $stats['objectives']],
            'course' => array_filter([
                'title' => $doc['course']['title'],
                'subtitle' => $doc['course']['subtitle'] ?? null,
                'objectives' => array_map($objective, $doc['course']['objectives'] ?? []),
            ], fn ($v) => $v !== null),
            'modules' => array_map(fn ($m) => array_filter([
                'id' => $m['id'],
                'title' => $m['title'],
                'summary' => $m['summary'] ?? null,
                'change' => $mark($m['id']),
                'lessons' => array_map(function ($l) use ($objective, $cite, $mark, $byId) {
                    $lesson = array_filter([
                        'id' => $l['id'],
                        'title' => $l['title'],
                        'summary' => $l['summary'] ?? null,
                        'minutes' => (int) $l['minutes'],
                        'change' => $mark($l['id']),
                        'objectives' => array_map($objective, $l['objectives'] ?? []),
                        'citations' => $cite($l['citations'] ?? []),
                    ], fn ($v) => $v !== null);
                    foreach ($byId[$l['id']]['fields'] ?? [] as $f) {
                        if ($f['field'] === 'title' && is_string($f['before'])) {
                            $lesson['before'] = $f['before'];
                        }
                    }

                    return $lesson;
                }, $m['lessons']),
            ], fn ($v) => $v !== null), $doc['modules']),
            'removed' => $removed,
            'comment' => $comment,
        ], fn ($v) => $v !== null);

        $this->emit($session, $run, "outline-{$version->id}", 'outline', [['id' => 'root', 'component' => 'OutlineDiff'] + $props], ['versionId' => $version->id]);
        if ($status !== 'proposed') {
            $this->close($session, "outline-{$version->id}");
        }
    }

    /** @param array<string,mixed> $props GenerationProgress props */
    public function progress(Session $session, Run $run, array $props): void
    {
        $this->emit($session, $run, "progress-{$run->id}", 'progress', [['id' => 'root', 'component' => 'GenerationProgress'] + $props], ['runId' => $run->id]);
    }

    /** @param array<string,mixed> $plan ApplySummary props without versionId/status */
    public function apply(Session $session, ?Run $run, Version $version, array $plan, string $status = 'proposed'): void
    {
        foreach ((array) $session->stateValue('surfaces', []) as $id => $surface) {
            if (($surface['kind'] ?? '') === 'apply' && $id !== "apply-{$version->id}" && !empty($surface['open'])) {
                $this->close($session, (string) $id);
            }
        }
        $props = ['versionId' => $version->id, 'status' => $status] + $plan;
        $this->emit($session, $run, "apply-{$version->id}", 'apply', [['id' => 'root', 'component' => 'ApplySummary'] + $props], ['versionId' => $version->id]);
        if ($status !== 'proposed') {
            $this->close($session, "apply-{$version->id}");
        }
    }

    /**
     * Proposed element change: always a DiffView (the approval surface); the model may ask for a
     * preview card of the new element next to it (validated against the catalogue).
     */
    public function patch(Session $session, ?Run $run, Version $version, string $elementId, string $label, string $reason, string $status, ?array $preview = null): void
    {
        $doc = $version->document;
        $parent = $version->parent?->document ?? [];
        $fragments = self::labels($doc);
        $changes = BlueprintDiff::forElement(BlueprintDiff::compare($parent, $doc), $elementId);
        $rows = [];
        foreach ($changes as $c) {
            foreach ($c['fields'] as $f) {
                $rows[] = array_filter([
                    'path' => $c['id'] . '.' . $f['field'],
                    'label' => $c['label'] . ' · ' . $f['label'],
                    'kind' => $c['kind'] === 'changed' ? 'changed' : $c['kind'],
                    'before' => is_scalar($f['before']) ? mb_substr(self::scalar($f['before']), 0, 20000) : null,
                    'after' => is_scalar($f['after']) ? mb_substr(self::scalar($f['after']), 0, 20000) : null,
                ], fn ($v) => $v !== null);
            }
        }
        $element = Blueprint::find($doc, $elementId);
        $cited = $element ? array_values(array_unique(array_filter(Blueprint::citations($element['node'])))) : [];
        $components = [
            ['id' => 'root', 'component' => 'Column', 'gap' => 'md', 'children' => $preview !== null ? ['diff', 'preview'] : ['diff']],
            ['id' => 'diff', 'component' => 'DiffView', 'versionId' => $version->id, 'elementId' => $elementId, 'elementLabel' => mb_substr($label, 0, 200),
                'reason' => mb_substr($reason, 0, 1000), 'status' => $status, 'changes' => array_slice($rows, 0, 100),
                'citations' => array_map(fn ($id) => ['fragmentId' => $id, 'label' => $fragments[$id] ?? $id], array_slice($cited, 0, 20))],
        ];
        if ($preview !== null) {
            $components[] = $preview;
        }
        $this->emit($session, $run, "patch-{$version->id}", 'patch', $components, ['versionId' => $version->id, 'elementId' => $elementId]);
        if ($status !== 'proposed') {
            $this->close($session, "patch-{$version->id}");
        }
    }

    private static function scalar(mixed $v): string
    {
        return is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v;
    }

    /** @return array<string,string> fragment id → "§2.3 Title" for the fragments a document cites */
    public static function labels(array $doc): array
    {
        $ids = Blueprint::citations($doc);
        if ($ids === []) {
            return [];
        }

        return Fragment::query()->whereIn('id', $ids)->get()->mapWithKeys(fn (Fragment $f) => [$f->id => mb_substr($f->label(), 0, 120)])->all();
    }

    public static function costProps(Session $session): array
    {
        $cost = Llm::cost($session);

        return [
            'usedMicroUsd' => $cost['usedMicroUsd'],
            'budgetMicroUsd' => $cost['budgetMicroUsd'],
            'inputTokens' => $cost['inputTokens'],
            'outputTokens' => $cost['outputTokens'],
            'cacheReadTokens' => $cost['cacheReadTokens'],
        ];
    }
}
