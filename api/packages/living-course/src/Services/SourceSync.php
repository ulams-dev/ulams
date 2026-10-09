<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Http\UploadedFile;
use Ulams\CourseBuilder\Models\Source;
use Ulams\CourseBuilder\Ingestion\ConvertedDocument;
use Ulams\CourseBuilder\Ingestion\SourceConverter;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\LivingCourse\Connectors\ConnectorException;
use Ulams\LivingCourse\Connectors\FetchedFile;
use Ulams\LivingCourse\Connectors\FetchResult;
use Ulams\LivingCourse\Connectors\Git\PathFilter;
use Ulams\LivingCourse\Connectors\SourceConnectorRegistry;
use Ulams\LivingCourse\Diff\FragmentChangeSet;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Revision;
use Ulams\Uploads\Exceptions\UploadRejected;

/**
 * Entry point of every way a source can change (ADR 0030): a new revision is stored, compared with
 * the synced one and classified. Connectors (Git, URL, plugins) end here too.
 */
final class SourceSync
{
    public function __construct(
        private readonly RevisionService $revisions,
        private readonly ChangeDetection $detection,
        private readonly SourceConnectorRegistry $connectors,
        private readonly SourceConverter $converter,
        private readonly AuditLog $audit,
        private readonly Notifier $notifier,
    ) {
    }

    /**
     * One check of a connected source: fetch, convert, store the revision, compare, classify. Failures
     * are counted on the connection (three in a row set it to `error` and tell the author); the
     * schedule moves on after every check.
     *
     * @return array{state:string,revision:?Revision}
     */
    public function check(Connection $connection, string $trigger, ?int $userId): array
    {
        $source = Source::query()->findOrFail($connection->source_id);
        $latest = $connection->latest_revision_id ? Revision::query()->find($connection->latest_revision_id) : null;
        try {
            $connector = $this->connectors->get($connection->connector);
            $fetched = $connector->fetch($connection, $latest);
            if ($fetched->unchanged) {
                $this->succeeded($connection);

                return ['state' => 'unchanged', 'revision' => $latest];
            }
            $documents = $this->documents($fetched->files);
            $raw = array_map(fn (FetchedFile $f) => [$f->path, $f->bytes, $f->blob], $fetched->files);
            $revision = $this->revisions->create($connection, $source, $documents, $raw, $connection->connector, $fetched->ref, $trigger, $userId, ['connector' => $connection->connector] + $fetched->metadata);
            $processed = $this->detection->process($revision, $userId);
            $this->succeeded($connection);

            return ['state' => $processed['revision']->status, 'revision' => $processed['revision']];
        } catch (ConnectorException|\RuntimeException $e) {
            $this->failed($connection, $e->getMessage(), $userId);

            return ['state' => 'failed', 'revision' => null];
        }
    }

    /** Revision 1 of a connected source: stored and promoted, so the builder reads its fragments. */
    public function firstRevision(Connection $connection, Source $source, FetchResult $fetched, int $userId): Revision
    {
        $documents = $this->documents($fetched->files);
        $raw = array_map(fn (FetchedFile $f) => [$f->path, $f->bytes, $f->blob], $fetched->files);
        $revision = $this->revisions->create($connection, $source, $documents, $raw, $connection->connector, $fetched->ref, 'initial', $userId, ['connector' => $connection->connector] + $fetched->metadata);
        $this->revisions->promote($revision, $userId);
        $this->succeeded($connection);

        return $revision->refresh();
    }

    private function succeeded(Connection $connection): void
    {
        $connection->forceFill([
            'last_checked_at' => now(), 'failure_count' => 0, 'last_error' => null, 'status' => $connection->status === 'error' ? 'active' : $connection->status,
            'next_check_at' => self::nextCheck($connection),
        ])->save();
    }

    private function failed(Connection $connection, string $message, ?int $userId): void
    {
        $count = $connection->failure_count + 1;
        $error = $count >= (int) config('living_course.poll.failures_before_error', 3);
        $connection->forceFill([
            'last_checked_at' => now(), 'failure_count' => $count, 'last_error' => mb_substr($message, 0, 500), 'status' => $error ? 'error' : $connection->status,
            // a connection in error backs off to daily checks
            'next_check_at' => self::nextCheck($connection, $error),
        ])->save();
        $this->audit->record('revision.failed', [
            'session_id' => $connection->session_id, 'subject_type' => 'connection', 'subject_id' => $connection->id, 'source_id' => $connection->source_id,
            'data' => ['reason' => mb_substr($message, 0, 300), 'failures' => $count], ...($userId === null ? ['actor_type' => 'system'] : ['actor_id' => $userId]),
        ]);
        if ($count === (int) config('living_course.poll.failures_before_error', 3)) {
            $this->notifier->checkFailing($connection);
        }
    }

    /** now + the schedule's interval + up to 10 % jitter; never below the configured minimum. */
    public static function nextCheck(Connection $connection, bool $backOff = false): ?\Illuminate\Support\Carbon
    {
        $minutes = match ($backOff ? 'daily' : $connection->schedule) {
            'hourly' => 60,
            'daily' => 1440,
            'weekly' => 10080,
            default => null,
        };
        if ($minutes === null) {
            return null;
        }
        $minutes = max($minutes, (int) config('living_course.poll.min_minutes', 60));

        return now()->addMinutes($minutes)->addSeconds(random_int(0, (int) ($minutes * 6)));
    }

    /**
     * @param FetchedFile[] $files
     * @return ConvertedDocument[]
     */
    private function documents(array $files): array
    {
        usort($files, fn (FetchedFile $a, FetchedFile $b) => strcmp($a->path, $b->path));
        $documents = [];
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file->path, PATHINFO_EXTENSION));
            if ($file->kind === 'markdown') {
                $text = $extension === 'mdx' ? PathFilter::stripMdx($file->bytes) : $file->bytes;
                $clean = SourceIngestor::cleanMarkdown($text);
                if (trim($clean) !== '') {
                    $documents[] = new ConvertedDocument($clean, [], ['kind' => 'markdown'], count($files) > 1 || $file->path !== '' ? $file->path : null);
                }
                continue;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'lcfile');
            file_put_contents($tmp, $file->bytes);
            try {
                $documents[] = $this->converter->toMarkdown($tmp, $file->kind, $file->path);
            } finally {
                @unlink($tmp);
            }
        }
        if ($documents === []) {
            throw new ConnectorException('No text could be read from the fetched files.');
        }

        return $documents;
    }

    /**
     * @return array{revision:Revision,created:bool,unchanged:bool,changes:?FragmentChangeSet} `created` is false when the file is the latest revision already; `unchanged` when nothing in the text changed
     * @throws UploadRejected
     */
    public function upload(Source $source, UploadedFile $file, ?int $userId): array
    {
        $created = $this->revisions->createFromUpload($source, $file, $userId);
        if ($created['unchanged']) {
            return ['revision' => $created['revision'], 'created' => false, 'unchanged' => true, 'changes' => null];
        }
        $processed = $this->detection->process($created['revision'], $userId);

        return ['revision' => $processed['revision'], 'created' => true, 'unchanged' => $processed['revision']->status === 'unchanged', 'changes' => $processed['changes']];
    }
}
