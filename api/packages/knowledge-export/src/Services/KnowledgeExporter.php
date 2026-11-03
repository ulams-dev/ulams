<?php

namespace Ulams\KnowledgeExport\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Ulams\Courses\Models\Course;
use Ulams\KnowledgeExport\Events\KnowledgeExported;
use Ulams\KnowledgeExport\Models\KnowledgeExport;

/**
 * Runs one export: builds the pack of a course, or of every learner-visible course, and writes it to the
 * configured disk. An automatic re-export that finds the content unchanged (same pack hash as the last ready
 * export) writes nothing and fires no event, so a receiver is told only when there is something new.
 */
class KnowledgeExporter
{
    public function __construct(private readonly CoursePackBuilder $builder)
    {
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(config('knowledge_export.disk') ?: config('filesystems.default'));
    }

    public static function path(KnowledgeExport $export, string $file): string
    {
        return $export->directory . '/' . $file;
    }

    /** The export row after the run, or null when an automatic run found nothing new (the row is removed). */
    public function execute(KnowledgeExport $export): ?KnowledgeExport
    {
        $export->forceFill(['status' => KnowledgeExport::RUNNING, 'error' => null])->save();
        try {
            $export->scope === KnowledgeExport::COURSE ? $this->course($export) : $this->tenant($export);
        } catch (SkippedUnchanged) {
            $export->delete();

            return null;
        } catch (Throwable $e) {
            report($e);
            $export->forceFill(['status' => KnowledgeExport::FAILED, 'error' => mb_substr($e->getMessage(), 0, 500), 'finished_at' => now()])->save();

            return $export;
        }
        $this->prune($export);
        event(new KnowledgeExported($export));

        return $export;
    }

    private function course(KnowledgeExport $export): void
    {
        $course = Course::query()->find($export->course_id);
        if ($export->trigger === 'auto' && ($course === null || !CoursePackBuilder::exportable($course))) {
            throw new SkippedUnchanged(); // unpublished or deleted since the change: nothing to keep up to date
        }
        $course ?? throw new \InvalidArgumentException('The course does not exist.');
        $pack = $this->builder->build($course);
        if ($export->trigger === 'auto' && $this->lastHash(KnowledgeExport::COURSE, $course->getKey(), $export->id) === $pack['hash']) {
            throw new SkippedUnchanged();
        }
        $directory = trim((string) config('knowledge_export.directory'), '/') . '/' . $export->id;
        $this->write($directory, $pack);
        $export->forceFill([
            'status' => KnowledgeExport::READY, 'directory' => $directory, 'pack_hash' => $pack['hash'], 'courses' => 1,
            'chunks' => count($pack['chunks']), 'uncited' => $pack['uncited'], 'finished_at' => now(),
        ])->save();
    }

    private function tenant(KnowledgeExport $export): void
    {
        $directory = trim((string) config('knowledge_export.directory'), '/') . '/' . $export->id;
        $index = [];
        $chunks = 0;
        $uncited = 0;
        // one course at a time: a large tenant never sits in memory as a whole
        foreach (Course::query()->active()->orderBy('id')->pluck('id') as $id) {
            $course = Course::query()->find($id);
            if ($course === null || !CoursePackBuilder::exportable($course)) {
                continue;
            }
            $pack = $this->builder->build($course);
            $this->write($directory . '/courses/' . $id, $pack);
            $chunks += count($pack['chunks']);
            $uncited += $pack['uncited'];
            $index[] = [
                'course_id' => $id, 'title' => (string) $course->title, 'pack_hash' => $pack['hash'], 'chunks' => count($pack['chunks']),
                'uncited_chunks' => $pack['uncited'], 'manifest' => "courses/{$id}/manifest.json", 'chunks_file' => "courses/{$id}/chunks.jsonl",
            ];
        }
        $hash = hash('sha256', json_encode(array_column($index, 'pack_hash'), JSON_THROW_ON_ERROR));
        self::disk()->put($directory . '/index.json', json_encode([
            'format' => CoursePackBuilder::FORMAT . '+index',
            'generated_at' => now()->toIso8601String(),
            'pack_hash' => $hash,
            'courses' => $index,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $export->forceFill([
            'status' => KnowledgeExport::READY, 'directory' => $directory, 'pack_hash' => $hash, 'courses' => count($index),
            'chunks' => $chunks, 'uncited' => $uncited, 'finished_at' => now(),
        ])->save();
    }

    /** @param array{manifest:array<string,mixed>,chunks:list<array<string,mixed>>} $pack */
    private function write(string $directory, array $pack): void
    {
        $lines = '';
        foreach ($pack['chunks'] as $chunk) {
            $lines .= json_encode($chunk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        }
        $disk = self::disk();
        $disk->put($directory . '/chunks.jsonl', $lines);
        $disk->put($directory . '/manifest.json', json_encode($pack['manifest'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function lastHash(string $scope, ?int $courseId, string $except): ?string
    {
        return KnowledgeExport::query()
            ->where('scope', $scope)->where('course_id', $courseId)->where('status', KnowledgeExport::READY)->where('id', '!=', $except)
            ->orderByDesc('finished_at')->orderByDesc('id')->value('pack_hash');
    }

    /** Keeps the newest `knowledge_export.keep` ready exports per course (or for the tenant), deleting older rows and files. */
    private function prune(KnowledgeExport $export): void
    {
        $old = KnowledgeExport::query()
            ->where('scope', $export->scope)->where('course_id', $export->course_id)->where('status', KnowledgeExport::READY)
            ->orderByDesc('finished_at')->orderByDesc('id')->get()->slice(max(1, (int) config('knowledge_export.keep', 5)));
        foreach ($old as $row) {
            if ($row->directory !== null) {
                self::disk()->deleteDirectory($row->directory);
            }
            $row->delete();
        }
    }
}
