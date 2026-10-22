<?php

namespace Ulams\CourseBuilder\CourseAsCode;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\BlueprintDiff;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Publish\LandingValidator;
use Ulams\CourseBuilder\Services\VersionService;

/**
 * Turns a pushed blueprint document into a **proposed** version of the kind `import` (ADR 0160).
 * Nothing reaches learners until a human approves the version and it is applied. The document is
 * untrusted input: it must pass the JSON Schema and the same deterministic checks as generated
 * content, and the server keeps what it owns (the source list). Works with AI off.
 *
 * A push based on the current version is a plain child. A push that is behind (the studio, a Living
 * Course update or another push moved the course on) is merged element by element with the current
 * version (`ElementMerge`, ADR 0160 and plan 7.4): disjoint changes are stored as one merged proposal on top of
 * the current version, overlapping changes refuse with BASE_OUTDATED and the conflicts, and store nothing.
 */
final class CourseImporter
{
    private const MAX_REPORTED_CHANGES = 200;

    public function __construct(
        private readonly VersionService $versions,
        private readonly SchemaRegistry $schemas,
        private readonly LandingValidator $landing,
        private readonly EventLog $events,
    ) {
    }

    /**
     * @param array<string,mixed> $document
     * @param array{userId:int,tokenId?:?string,tokenKind?:?string,client?:?string,commit?:?string,changeUrl?:?string} $actor
     * @return array{unchanged:bool,version:?Version,changes:array,counts:array,warnings:string[],handEdited:string[],merged:?array}
     */
    public function import(Session $session, array $document, string $baseVersionId, ?string $message, array $actor): array
    {
        $current = $session->currentVersion;
        if ($current === null || !in_array($current->kind, VersionService::CONTENT_KINDS, true)) {
            throw new CourseAsCodeException('This course has no content blueprint to import into yet.', 409, 'NO_BLUEPRINT');
        }
        $base = Version::query()->where('session_id', $session->id)->find($baseVersionId);
        if ($base === null) {
            throw new CourseAsCodeException('baseVersionId is not a version of this course.', 422, 'BASE_UNKNOWN');
        }
        $merged = null;
        $pushed = Blueprint::upgrade($document);
        if ($base->id !== $current->id) {
            $merge = ElementMerge::merge(Blueprint::upgrade($base->document), $pushed, Blueprint::upgrade($current->document));
            if ($merge['conflicts'] !== []) {
                $remote = BlueprintDiff::compare($base->document, $current->document);
                throw new CourseAsCodeException('Your folder and the server changed the same elements since your base version; pull and resolve the conflicts first.', 409, 'BASE_OUTDATED', [
                    'currentVersionId' => $current->id,
                    'currentVersionNumber' => $current->number,
                    'remote' => array_slice($remote, 0, self::MAX_REPORTED_CHANGES),
                    'conflicts' => $this->describeConflicts($merge['conflicts'], [$pushed, $current->document, $base->document]),
                ]);
            }
            $document = $merge['doc'];
            $merged = [
                'baseVersionId' => $base->id,
                'baseVersionNumber' => $base->number,
                'currentVersionId' => $current->id,
                'currentVersionNumber' => $current->number,
                'fromPush' => array_slice($merge['fromOurs'], 0, self::MAX_REPORTED_CHANGES),
                'fromServer' => array_slice($merge['fromTheirs'], 0, self::MAX_REPORTED_CHANGES),
                'notes' => $merge['notes'],
            ];
        }

        $warnings = [];
        $document = Blueprint::upgrade($document);
        // the server owns the source list: a push cannot invent sources or fragment counts
        $document['sources'] = $current->document['sources'] ?? [];

        $errors = $this->schemas->validateBlueprint($document);
        if ($errors === []) {
            $known = array_fill_keys($session->fragmentIds(), true);
            $checks = Checks::blueprint($document, $known);
            $errors = array_values(array_filter($checks, fn ($e) => !str_starts_with($e, 'warning: ')));
            $warnings = array_values(array_map(fn ($e) => substr($e, 9), array_filter($checks, fn ($e) => str_starts_with($e, 'warning: '))));
            $errors = [...$errors, ...$this->pages($document, $current->document)];
        }
        if ($errors !== []) {
            throw new CourseAsCodeException('The blueprint is not valid.', 422, 'INVALID_BLUEPRINT', ['errors' => array_slice($errors, 0, 50)]);
        }

        if (self::canonical($document) === self::canonical($current->document)) {
            return ['unchanged' => true, 'version' => null, 'changes' => [], 'counts' => ['added' => 0, 'changed' => 0, 'removed' => 0], 'warnings' => $warnings, 'handEdited' => [], 'merged' => $merged];
        }

        $changes = BlueprintDiff::compare($current->document, $document);
        $version = $this->versions->create($session, $document, 'import', 'author', Version::PROPOSED, $current, $message ?: ($merged !== null
            ? "Merged from a course repository (based on version {$base->number}, the server was at version {$current->number})"
            : 'Imported from a course repository'));
        $this->events->custom($session, null, 'course_as_code.imported', array_filter([
            'versionId' => $version->id,
            'number' => $version->number,
            'actorId' => $actor['userId'],
            'tokenId' => $actor['tokenId'] ?? null,
            'tokenKind' => $actor['tokenKind'] ?? null,
            'client' => $actor['client'] ?? null,
            'commit' => $actor['commit'] ?? null,
            'changeUrl' => $actor['changeUrl'] ?? null,
            'originChanges' => self::originChanges($changes),
            'mergedWith' => $merged['currentVersionId'] ?? null,
        ], fn ($v) => $v !== null && $v !== []));

        return [
            'unchanged' => false,
            'version' => $version,
            'changes' => array_slice($changes, 0, self::MAX_REPORTED_CHANGES),
            'counts' => BlueprintDiff::counts($changes),
            'warnings' => $warnings,
            'handEdited' => self::handEdited($changes, BlueprintDiff::flatten($current->document), BlueprintDiff::flatten($document)),
            'merged' => $merged,
        ];
    }

    /**
     * What the 409 tells the client about each conflict: enough to name the element and the fields, with long text cut.
     *
     * @param array<int,array<string,mixed>> $conflicts
     * @param array<int,array<string,mixed>> $documents where an element can be named: pushed, current, base
     * @return array<int,array<string,mixed>>
     */
    private function describeConflicts(array $conflicts, array $documents): array
    {
        $labels = [];
        foreach (array_reverse($documents) as $doc) {
            foreach (BlueprintDiff::flatten($doc) as $id => $node) {
                $labels[$id] = $node['label'];
            }
        }
        $cut = fn (mixed $v) => is_string($v) && mb_strlen($v) > 400 ? mb_substr($v, 0, 397) . '...' : $v;

        return array_map(fn (array $c) => [
            'elementId' => $c['id'],
            'type' => $c['type'],
            'label' => $labels[$c['id']] ?? $c['id'],
            'kind' => $c['kind'],
            'field' => $c['fields'][0]['field'] ?? null,
            'fields' => array_map(fn ($f) => ['field' => $f['field'], 'base' => $cut($f['base']), 'ours' => $cut($f['ours']), 'theirs' => $cut($f['theirs'])], array_slice($c['fields'], 0, 12)),
            'held' => count($c['held']),
        ], array_slice($conflicts, 0, 50));
    }

    /** The landing and header documents may change in an import, but they are validated like generated ones. @return string[] */
    private function pages(array $document, array $current): array
    {
        $errors = [];
        $new = $document['pages']['landing'] ?? null;
        if ($new !== null && $new !== ($current['pages']['landing'] ?? null)) {
            $errors = $this->landing->validate($new);
        }

        return $errors;
    }

    /** `meta` is ignored by apply and never diffed, so a push that only touches it is "unchanged". */
    private static function canonical(array $document): string
    {
        $strip = function (mixed $node) use (&$strip) {
            if (!is_array($node)) {
                return $node;
            }
            unset($node['meta']);

            return array_map($strip, $node);
        };

        return Blueprint::fingerprint($strip($document));
    }

    /**
     * Elements whose `origin` flipped in this import: a human says "I wrote this" (ai to author) or hands text back.
     *
     * @return array<int,array{id:string,from:string,to:string}>
     */
    private static function originChanges(array $changes): array
    {
        $out = [];
        foreach ($changes as $change) {
            foreach ($change['fields'] as $field) {
                if ($change['kind'] === 'changed' && $field['field'] === 'origin') {
                    $out[] = ['id' => $change['id'], 'from' => (string) $field['before'], 'to' => (string) $field['after']];
                }
            }
        }

        return $out;
    }

    /**
     * Labels of AI-written elements whose text a human changed outside the AI pipeline, for reviewers.
     *
     * @param array<string,array{fields:array<string,mixed>}> $before flattened current document
     * @param array<string,array{fields:array<string,mixed>}> $after flattened pushed document
     * @return string[]
     */
    private static function handEdited(array $changes, array $before, array $after): array
    {
        $text = ['title', 'summary', 'text', 'markdown', 'stem', 'explanation', 'caption', 'data'];
        $out = [];
        foreach ($changes as $change) {
            if ($change['kind'] !== 'changed') {
                continue;
            }
            $changed = array_column($change['fields'], 'field');
            $was = $before[$change['id']]['fields']['origin'] ?? null;
            $now = $after[$change['id']]['fields']['origin'] ?? null;
            if ($was === 'ai' && $now === 'ai' && array_intersect($text, $changed) !== []) {
                $out[] = $change['label'];
            }
        }

        return $out;
    }
}
