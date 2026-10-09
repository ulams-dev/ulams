<?php

namespace Ulams\CourseBuilder\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Ulams\CourseBuilder\Blueprint\BlueprintDiff;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;

/**
 * Blueprint versions: every stage and every edit is a version with an element-aware diff from its
 * parent. Undo and redo move along the chain of approved content versions; restore creates a new
 * version from an old one. Outline versions are not undo targets once content exists.
 */
final class VersionService
{
    public const CONTENT_KINDS = ['content', 'patch', 'author', 'restore'];

    public function create(
        Session $session,
        array $document,
        string $kind,
        string $origin,
        string $status,
        ?Version $parent = null,
        ?string $reason = null,
        ?string $elementId = null,
        array $callIds = [],
        ?int $decidedBy = null,
    ): Version {
        return DB::transaction(function () use ($session, $document, $kind, $origin, $status, $parent, $reason, $elementId, $callIds, $decidedBy) {
            Session::query()->whereKey($session->id)->lockForUpdate()->first(['id']);
            $number = (int) Version::query()->where('session_id', $session->id)->max('number') + 1;
            $diff = null;
            if ($parent !== null) {
                $changes = BlueprintDiff::compare($parent->document, $document);
                $diff = ['counts' => BlueprintDiff::counts($changes), 'elements' => array_map(fn ($c) => ['id' => $c['id'], 'type' => $c['type'], 'kind' => $c['kind'], 'label' => $c['label']], array_slice($changes, 0, 500))];
            }

            return Version::query()->create([
                'session_id' => $session->id,
                'number' => $number,
                'parent_id' => $parent?->id,
                'schema_version' => (int) ($document['schemaVersion'] ?? 1),
                'kind' => $kind,
                'document' => $document,
                'diff_from_parent' => $diff,
                'origin' => $origin,
                'reason' => $reason !== null ? mb_substr($reason, 0, 2000) : null,
                'status' => $status,
                'element_id' => $elementId,
                'decided_by' => $status === Version::APPROVED ? $decidedBy : null,
                'decided_at' => $status === Version::APPROVED ? now() : null,
                'ai_call_ids' => $callIds ?: null,
            ]);
        });
    }

    public function approve(Version $version, ?int $userId): Version
    {
        if ($version->status !== Version::PROPOSED) {
            throw new InvalidArgumentException('Only a proposed version can be approved.');
        }
        $version->forceFill(['status' => Version::APPROVED, 'decided_by' => $userId, 'decided_at' => now()])->save();
        // other open proposals for the same element (or the same stage) are now stale
        Version::query()->where('session_id', $version->session_id)->where('status', Version::PROPOSED)
            ->where('id', '!=', $version->id)->where('kind', $version->kind)
            ->update(['status' => Version::SUPERSEDED]);

        return $version;
    }

    public function reject(Version $version, ?int $userId): Version
    {
        if ($version->status !== Version::PROPOSED) {
            throw new InvalidArgumentException('Only a proposed version can be rejected.');
        }
        $version->forceFill(['status' => Version::REJECTED, 'decided_by' => $userId, 'decided_at' => now()])->save();

        return $version;
    }

    public function setCurrent(Session $session, Version $version, bool $clearRedo = true): void
    {
        $session->current_version_id = $version->id;
        if ($clearRedo) {
            $session->putState('redo', []);
        }
        $session->save();
    }

    /** @return Version|null the version undo moves to */
    public function undoTarget(Session $session): ?Version
    {
        $current = $session->currentVersion;
        $parent = $current?->parent;
        while ($parent !== null && !($parent->status === Version::APPROVED && in_array($parent->kind, self::CONTENT_KINDS, true))) {
            $parent = $parent->parent;
        }

        return $parent;
    }

    public function undo(Session $session): Version
    {
        $target = $this->undoTarget($session);
        if ($target === null) {
            throw new InvalidArgumentException('Nothing to undo.');
        }
        $redo = (array) $session->stateValue('redo', []);
        $redo[] = $session->current_version_id;
        $session->putState('redo', $redo);
        $this->setCurrent($session, $target, false);

        return $target;
    }

    public function redo(Session $session): Version
    {
        $redo = (array) $session->stateValue('redo', []);
        $id = array_pop($redo);
        $version = $id ? Version::query()->where('session_id', $session->id)->find($id) : null;
        if ($version === null) {
            throw new InvalidArgumentException('Nothing to redo.');
        }
        $session->putState('redo', $redo);
        $this->setCurrent($session, $version, false);

        return $version;
    }

    public function restore(Session $session, Version $source, ?int $userId): Version
    {
        if (!in_array($source->kind, self::CONTENT_KINDS, true) || $source->status === Version::PROPOSED) {
            throw new InvalidArgumentException('Only an approved content version can be restored.');
        }
        $version = $this->create($session, $source->document, 'restore', 'restore', Version::APPROVED, $session->currentVersion, "Restored version {$source->number}", null, [], $userId);
        $this->setCurrent($session, $version);

        return $version;
    }

    /** @return array<int,array<string,mixed>> history rows for the VersionList */
    public function history(Session $session): array
    {
        return $session->versions()->get()->map(fn (Version $v) => array_filter([
            'id' => $v->id,
            'number' => $v->number,
            'kind' => $v->kind,
            'origin' => $v->origin,
            'status' => $v->status,
            'reason' => $v->reason ? mb_substr($v->reason, 0, 1000) : null,
            'createdAt' => $v->created_at?->toIso8601String(),
        ], fn ($x) => $x !== null))->all();
    }
}
