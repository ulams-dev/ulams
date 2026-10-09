<?php

namespace Ulams\CourseBuilder\Transfer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Phar;
use PharData;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\CourseBuilder\Models\Version;

/**
 * Moves a builder session between tenants (ADR 0048): a tar with the brief, the current and applied
 * versions, the sources with their raw files and every fragment. Fragment ids and the element ids of
 * the blueprint are kept, so every citation resolves exactly as before; sessions, sources and versions
 * get new ids in the target tenant. Nothing of the LMS is copied: the author applies the course again
 * in the new site.
 */
final class SessionArchive
{
    public const FORMAT = 'ulams-builder-session/1';

    private const SOURCE_FIELDS = ['original_name', 'mime', 'size', 'sha256', 'status', 'metadata', 'token_estimate', 'error'];
    private const VERSION_FIELDS = ['kind', 'schema_version', 'document', 'origin', 'reason', 'status'];

    /** Writes the archive to `$path` (a `.tar` file). */
    public function export(Session $session, string $path): void
    {
        if (!str_ends_with($path, '.tar')) {
            throw new InvalidArgumentException('The archive path must end in .tar.');
        }
        @unlink($path);
        $tar = new PharData($path, 0, null, Phar::TAR);
        $disk = Storage::disk(SourceIngestor::disk());

        $versionIds = array_values(array_unique(array_filter([$session->current_version_id, $session->applied_version_id])));
        $versions = Version::query()->whereIn('id', $versionIds)->orderBy('number')->get();
        $sources = $session->sources()->get();

        $tar->addFromString('manifest.json', $this->json([
            'format' => self::FORMAT,
            'exportedAt' => now()->toIso8601String(),
            'sessionId' => $session->id,
            'title' => $session->title,
            'status' => $session->status,
            'brief' => $session->brief,
            'briefVersion' => $session->brief_version,
            'currentVersionId' => $session->current_version_id,
            'appliedVersionId' => $session->applied_version_id,
        ]));
        $tar->addFromString('versions.json', $this->json($versions->map(fn (Version $v) => ['id' => $v->id, 'number' => $v->number] + $v->only(self::VERSION_FIELDS))->all()));
        $manifestSources = [];
        foreach ($sources as $source) {
            $manifestSources[] = ['id' => $source->id] + $source->only(self::SOURCE_FIELDS) + [
                'fragments' => $source->fragments()->get()->map(fn (Fragment $f) => $f->toArray())->all(),
            ];
            $tar->addFromString("files/{$source->id}/raw", (string) $disk->get($source->path));
            if ($source->markdown_path !== null && $disk->exists($source->markdown_path)) {
                $tar->addFromString("files/{$source->id}/markdown", (string) $disk->get($source->markdown_path));
            }
        }
        $tar->addFromString('sources.json', $this->json($manifestSources));
    }

    /**
     * Creates the session for `$authorId` in this tenant from the archive.
     *
     * @throws InvalidArgumentException when the file is not a session archive
     */
    public function import(string $path, int $authorId): Session
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("No archive at {$path}.");
        }
        $tar = new PharData($path);
        $read = fn (string $name) => isset($tar[$name]) ? (string) $tar[$name]->getContent() : throw new InvalidArgumentException("The archive has no {$name}.");
        $manifest = (array) json_decode($read('manifest.json'), true);
        if (($manifest['format'] ?? null) !== self::FORMAT) {
            throw new InvalidArgumentException('This is not a builder session archive.');
        }
        $versions = (array) json_decode($read('versions.json'), true);
        $sources = (array) json_decode($read('sources.json'), true);
        $disk = Storage::disk(SourceIngestor::disk());

        return DB::transaction(function () use ($manifest, $versions, $sources, $authorId, $tar, $disk) {
            $session = Session::query()->create([
                'author_id' => $authorId,
                'title' => $manifest['title'],
                'status' => Session::DRAFT,
                'brief' => $manifest['brief'],
                'brief_version' => (int) ($manifest['briefVersion'] ?? 0),
                'state' => ['importedFrom' => ['sessionId' => $manifest['sessionId'], 'at' => now()->toIso8601String()]],
            ]);

            $idMap = [];
            foreach ($sources as $row) {
                $source = Source::query()->create(array_intersect_key($row, array_flip(self::SOURCE_FIELDS)) + ['session_id' => $session->id, 'path' => '']);
                $idMap[$row['id']] = $source->id;
                $path = "course-builder/sources/{$session->id}/" . ($row['sha256'] ?? Str::random(16));
                $disk->put($path, (string) $tar["files/{$row['id']}/raw"]->getContent());
                $source->path = $path;
                if (isset($tar["files/{$row['id']}/markdown"])) {
                    $source->markdown_path = $path . '.md';
                    $disk->put($source->markdown_path, (string) $tar["files/{$row['id']}/markdown"]->getContent());
                }
                $source->save();
                foreach (array_chunk($row['fragments'], 200) as $chunk) {
                    Fragment::query()->insert(array_map(fn ($f) => array_merge($f, [
                        'source_id' => $source->id,
                        'heading_path' => json_encode($f['heading_path'], JSON_UNESCAPED_UNICODE),
                    ]), $chunk));
                }
            }

            $versionMap = [];
            foreach ($versions as $i => $row) {
                $document = json_decode(strtr((string) json_encode($row['document'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $idMap), true);
                $version = Version::query()->create(array_intersect_key($row, array_flip(self::VERSION_FIELDS)) + [
                    'session_id' => $session->id,
                    'number' => $i + 1,
                    'document' => $document,
                    'schema_version' => $row['schema_version'] ?? 1,
                ]);
                $version->document = $document;
                $version->save();
                $versionMap[$row['id']] = $version->id;
            }

            $current = $versionMap[$manifest['currentVersionId']] ?? null;
            $currentKind = $current !== null ? Version::query()->find($current)?->kind : null;
            $session->current_version_id = $current;
            $session->status = match (true) {
                $currentKind === null => Session::DRAFT,
                $currentKind === 'outline' => Session::OUTLINE_REVIEW,
                default => Session::APPLY_REVIEW,
            };
            $session->save();

            return $session;
        });
    }

    private function json(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
