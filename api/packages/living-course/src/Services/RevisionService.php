<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Ulams\CourseBuilder\Ingestion\ConvertedDocument;
use Ulams\CourseBuilder\Ingestion\SourceConverter;
use Ulams\CourseBuilder\Ingestion\SourceDocumentBuilder;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\LivingCourse\Diff\Normaliser;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Models\RevisionFragment;
use Ulams\Uploads\Exceptions\UploadRejected;

/**
 * Source revisions (ADR 0030): revision 1 is the state the course was built from (copied from the
 * live fragments); later revisions are created by connectors. The live fragment table always
 * holds the synced revision; only `promote()` writes it.
 */
final class RevisionService
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly SourceIngestor $ingestor,
        private readonly SourceConverter $converter,
        private readonly SourceDocumentBuilder $builder,
    ) {
    }

    /**
     * Creates the implicit `upload` connection and revision 1 of a source from its live fragments.
     * Idempotent: a source that already has a synced revision is returned as it is.
     */
    public function ensureInitial(Source $source, ?int $userId = null): Revision
    {
        $connection = Connection::query()->where('source_id', $source->id)->first();
        if ($connection?->synced_revision_id !== null) {
            return Revision::query()->findOrFail($connection->synced_revision_id);
        }
        $session = Session::withTrashed()->findOrFail($source->session_id);

        return DB::transaction(function () use ($source, $session, $connection, $userId) {
            $connection ??= Connection::query()->create([
                'session_id' => $session->id,
                'source_id' => $source->id,
                'connector' => 'upload',
                'config' => [],
                'webhook_id' => Connection::newWebhookId(),
                'schedule' => 'manual',
                'auto_analyse' => true,
                'settings' => ['show_pending_to_learners' => false, 'notify_learners_of_updates' => true],
                'status' => 'active',
                'created_by' => $userId ?? $session->author_id,
            ]);
            $fragments = Fragment::query()->where('source_id', $source->id)->orderBy('ordinal')->get();
            $markdown = $source->markdown_path !== null ? Storage::disk(SourceIngestor::disk())->get($source->markdown_path) : null;
            $revision = Revision::query()->create([
                'source_id' => $source->id,
                'connection_id' => $connection->id,
                'number' => 1,
                'origin' => 'initial',
                'origin_ref' => $source->sha256,
                'trigger' => 'initial',
                'triggered_by' => $userId ?? $session->author_id,
                'status' => 'ingested',
                'raw_path' => $source->path,
                'markdown_path' => $source->markdown_path,
                'normalised_sha256' => Normaliser::hash($markdown ?? $fragments->pluck('text')->implode("\n\n")),
                'metadata' => ['name' => $source->original_name, 'sha256' => $source->sha256, 'size' => (int) $source->size, 'title' => $source->metadata['title'] ?? null, 'sourceMeta' => $source->metadata],
                'fragment_count' => $fragments->count(),
                'token_estimate' => (int) $source->token_estimate,
                'detected_at' => now(),
            ]);
            foreach ($fragments as $f) {
                RevisionFragment::query()->create([
                    'revision_id' => $revision->id,
                    'fragment_id' => $f->id,
                    'file_path' => $f->file_path,
                    'ordinal' => $f->ordinal,
                    'heading_path' => $f->heading_path,
                    'section' => $f->section,
                    'level' => $f->level,
                    'text' => $f->text,
                    'char_start' => $f->char_start,
                    'char_end' => $f->char_end,
                    'page_start' => $f->page_start,
                    'page_end' => $f->page_end,
                    'token_estimate' => $f->token_estimate,
                    'content_hash' => $f->content_hash,
                    'normalised_hash' => Normaliser::hash($f->text),
                ]);
            }
            $connection->forceFill(['synced_revision_id' => $revision->id, 'latest_revision_id' => $revision->id])->save();
            $this->audit->record('connection.created', [
                'session_id' => $session->id, 'subject_type' => 'connection', 'subject_id' => $connection->id, 'source_id' => $source->id,
                'revision_id' => $revision->id, 'origin_ref' => $revision->origin_ref,
                'data' => ['connector' => $connection->connector, 'revision' => 1, 'fragments' => $revision->fragment_count],
                ...($userId === null ? ['actor_type' => 'system'] : []),
            ]);

            return $revision;
        });
    }

    /**
     * A new version of a source uploaded by the author (any supported type may replace any other).
     * Goes through the upload guard and the converters of the builder, exactly like the first upload.
     *
     * @return array{revision:Revision,unchanged:bool}
     * @throws UploadRejected
     */
    public function createFromUpload(Source $source, UploadedFile $file, ?int $userId): array
    {
        $info = $this->ingestor->inspect($file);
        $latest = $this->ensureInitial($source, $userId);
        $connection = Connection::query()->where('source_id', $source->id)->firstOrFail();
        $latest = $connection->latest_revision_id ? Revision::query()->findOrFail($connection->latest_revision_id) : $latest;
        if ($latest->origin_ref === $info['sha256']) {
            $connection->forceFill(['last_checked_at' => now()])->save();

            return ['revision' => $latest, 'unchanged' => true];
        }
        $bytes = (string) file_get_contents((string) $file->getRealPath());
        $tmp = tempnam(sys_get_temp_dir(), 'lcrev');
        file_put_contents($tmp, $bytes);
        try {
            $document = $this->converter->toMarkdown($tmp, $info['kind']);
        } finally {
            @unlink($tmp);
        }
        $revision = $this->create($connection, $source, [$document], [[$info['name'], $bytes]], 'upload', $info['sha256'], 'upload', $userId, [
            'name' => $info['name'], 'kind' => $info['kind'], 'mime' => $info['mime'], 'size' => $info['size'],
        ]);

        return ['revision' => $revision, 'unchanged' => false];
    }

    /**
     * Stores one fetched state of a source: raw files, normalised Markdown and its own copy of
     * every fragment. Connectors call this with the documents they converted. Ids are computed with
     * the source row id, so unchanged heading positions keep their fragment ids.
     *
     * @param ConvertedDocument[] $documents
     * @param array<int,array{0:string,1:string}> $rawFiles [file name or path, bytes]
     * @param array<string,mixed> $meta
     * @throws RuntimeException
     */
    public function create(Connection $connection, Source $source, array $documents, array $rawFiles, string $origin, string $ref, string $trigger, ?int $userId, array $meta = []): Revision
    {
        $built = $this->builder->rows($source, $documents);
        $limit = (int) config('course_builder.limits.source_tokens', 400000);
        if ($built['tokens'] > $limit) {
            throw new RuntimeException(sprintf('The source adds up to about %d tokens; the limit is %d. Use a shorter document or narrow the paths.', $built['tokens'], $limit));
        }
        $disk = Storage::disk(SourceIngestor::disk());

        return DB::transaction(function () use ($connection, $source, $built, $rawFiles, $origin, $ref, $trigger, $userId, $meta, $disk) {
            Source::query()->whereKey($source->id)->lockForUpdate()->first(['id']);
            $number = (int) Revision::query()->where('source_id', $source->id)->max('number') + 1;
            $dir = trim((string) config('living_course.revisions_prefix'), '/') . "/{$source->id}/{$number}";
            $files = [];
            foreach ($rawFiles as [$name, $bytes]) {
                $safe = ltrim((string) preg_replace('/[^A-Za-z0-9._\/-]+/', '_', str_replace('..', '_', $name)), '/') ?: 'file';
                $disk->put("{$dir}/raw/{$safe}", $bytes);
                $files[] = ['path' => $name, 'sha256' => hash('sha256', $bytes), 'size' => strlen($bytes)];
            }
            $markdownPath = "{$dir}/source.md";
            $disk->put($markdownPath, $built['markdown']);
            $revision = Revision::query()->create([
                'source_id' => $source->id,
                'connection_id' => $connection->id,
                'number' => $number,
                'origin' => $origin,
                'origin_ref' => mb_substr($ref, 0, 128),
                'trigger' => $trigger,
                'triggered_by' => $userId,
                'status' => 'ingested',
                'raw_path' => count($files) === 1 ? $dir . '/raw/' . ltrim((string) preg_replace('/[^A-Za-z0-9._\/-]+/', '_', str_replace('..', '_', $files[0]['path'])), '/') : "{$dir}/raw",
                'markdown_path' => $markdownPath,
                'normalised_sha256' => Normaliser::hash($built['markdown']),
                'metadata' => $meta + ['title' => $built['meta']['title'] ?? null, 'language' => $built['meta']['language'] ?? null, 'files' => $files, 'sourceMeta' => $built['meta']],
                'fragment_count' => count($built['rows']),
                'token_estimate' => $built['tokens'],
                'detected_at' => now(),
            ]);
            $indexed = array_map(fn (array $row, int $i) => $row + ['global' => $i], $built['rows'], array_keys($built['rows']));
            foreach (array_chunk($indexed, 200) as $chunk) {
                RevisionFragment::query()->insert(array_map(fn (array $row) => [
                    'revision_id' => $revision->id,
                    'fragment_id' => $row['id'],
                    'file_path' => $row['file_path'] ?? null,
                    'ordinal' => $row['global'],
                    'heading_path' => json_encode($row['heading_path'], JSON_UNESCAPED_UNICODE),
                    'section' => $row['section'],
                    'level' => $row['level'],
                    'text' => $row['text'],
                    'char_start' => $row['char_start'],
                    'char_end' => $row['char_end'],
                    'page_start' => $row['page_start'],
                    'page_end' => $row['page_end'],
                    'token_estimate' => $row['token_estimate'],
                    'content_hash' => $row['content_hash'],
                    'normalised_hash' => Normaliser::hash($row['text']),
                ], $chunk));
            }
            $connection->forceFill(['latest_revision_id' => $revision->id, 'last_checked_at' => now()])->save();

            return $revision;
        });
    }

    /**
     * Makes a revision the one the course reflects: its fragments become the live fragments of the
     * source (the text citations and prompts read), and the synced pointer moves. Fragments that
     * disappear stay readable through the fragment archive (ADR 0030). The only writer of the live
     * fragment table for synced sources.
     */
    public function promote(Revision $revision, ?int $userId = null): void
    {
        $source = Source::query()->findOrFail($revision->source_id);
        $connection = Connection::query()->findOrFail($revision->connection_id);
        $rows = RevisionFragment::query()->where('revision_id', $revision->id)->orderBy('ordinal')->get()->map(fn (RevisionFragment $f) => [
            'id' => $f->fragment_id, 'source_id' => $source->id, 'file_path' => $f->file_path, 'heading_path' => $f->heading_path,
            'section' => $f->section, 'level' => $f->level, 'text' => $f->text, 'char_start' => $f->char_start, 'char_end' => $f->char_end,
            'page_start' => $f->page_start, 'page_end' => $f->page_end, 'token_estimate' => $f->token_estimate, 'content_hash' => $f->content_hash,
        ])->all();
        $meta = array_merge((array) $source->metadata, (array) ($revision->metadata['sourceMeta'] ?? []));
        $files = (array) ($revision->metadata['files'] ?? []);

        DB::transaction(function () use ($source, $connection, $revision, $rows, $meta, $files, $userId) {
            $this->ingestor->writeLive($source, $rows, $meta, (string) $revision->markdown_path, (int) $revision->token_estimate);
            if ($revision->origin !== 'initial' && count($files) === 1 && $revision->raw_path !== null) {
                // single-file sources keep pointing at their current file (native PDF blocks read it)
                $source->forceFill([
                    'path' => $revision->raw_path,
                    'sha256' => $files[0]['sha256'],
                    'size' => $files[0]['size'],
                    'mime' => (string) ($revision->metadata['mime'] ?? $source->mime),
                ])->save();
            }
            $connection->forceFill(['synced_revision_id' => $revision->id])->save();
            $this->audit->record('revision.promoted', [
                'session_id' => $connection->session_id, 'subject_type' => 'revision', 'subject_id' => $revision->id, 'source_id' => $source->id,
                'revision_id' => $revision->id, 'origin_ref' => $revision->origin_ref, 'data' => ['revision' => $revision->number, 'fragments' => count($rows)],
                ...($userId === null ? ['actor_type' => 'system'] : ['actor_id' => $userId]),
            ]);
        });
    }
}
