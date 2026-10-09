<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\LivingCourse\Diff\Normaliser;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Models\RevisionFragment;

/**
 * Source revisions (ADR 0030): revision 1 is the state the course was built from (copied from the
 * live fragments); later revisions are created by connectors. The live fragment table always
 * holds the synced revision; only `promote()` writes it.
 */
final class RevisionService
{
    public function __construct(private readonly AuditLog $audit)
    {
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
                'metadata' => ['name' => $source->original_name, 'sha256' => $source->sha256, 'size' => (int) $source->size, 'title' => $source->metadata['title'] ?? null],
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
}
