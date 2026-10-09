<?php

namespace Ulams\CourseBuilder\Ingestion;

use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\Uploads\Exceptions\UploadRejected;
use Ulams\Uploads\UploadGuard;

/**
 * Upload → private storage → Source Document with fragments. No LLM involved.
 *
 * Uploads go through the uploads package (size, extension, sniffed MIME, virus scan), plus a check
 * that the extension and the sniffed type agree; DOCX archives are inspected for zip-slip and zip
 * bombs before anything is read. Files are stored on a private disk, never through the public
 * files package.
 */
final class SourceIngestor
{
    public const UPLOAD_KIND = 'course-builder-source';

    private const TYPES = [
        'markdown' => ['extensions' => ['md', 'markdown', 'txt'], 'mime_prefix' => 'text/'],
        'pdf' => ['extensions' => ['pdf'], 'mimes' => ['application/pdf']],
        'docx' => ['extensions' => ['docx'], 'mimes' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream']],
    ];

    public function __construct(private readonly UploadGuard $guard)
    {
    }

    /** @return array<string,mixed> the uploads-package policy registered for builder sources */
    public static function policy(): array
    {
        return [
            'extensions' => ['md', 'markdown', 'txt', 'pdf', 'docx'],
            'mimes' => [
                'text/plain', 'text/markdown', 'text/x-markdown', 'text/html', 'text/x-c', 'text/x-c++', 'text/x-java', 'text/x-script.python', 'text/x-php',
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream',
            ],
            'max_size' => (int) config('course_builder.limits.source_bytes'),
        ];
    }

    /** @throws UploadRejected */
    public function store(Session $session, UploadedFile $file): Source
    {
        $this->guard->check($file, self::UPLOAD_KIND);
        $name = $file->getClientOriginalName();
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $sniffed = (string) (new finfo(FILEINFO_MIME_TYPE))->file((string) $file->getRealPath());
        $kind = self::kindFor($extension, $sniffed);
        if ($kind === null) {
            throw new UploadRejected('wrong_type', sprintf('The file extension .%s does not match its content (%s).', $extension, $sniffed));
        }

        $sha = (string) hash_file('sha256', (string) $file->getRealPath());
        $existing = Source::query()->where('session_id', $session->id)->where('sha256', $sha)->first();
        if ($existing !== null) {
            return $existing;
        }

        $path = "course-builder/sources/{$session->id}/{$sha}";
        Storage::disk(self::disk())->put($path, (string) file_get_contents((string) $file->getRealPath()));

        return Source::query()->create([
            'session_id' => $session->id,
            'original_name' => mb_substr($name, 0, 255),
            'mime' => $kind === 'docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : ($kind === 'pdf' ? 'application/pdf' : 'text/markdown'),
            'size' => (int) $file->getSize(),
            'sha256' => $sha,
            'path' => $path,
            'status' => 'uploaded',
        ]);
    }

    public static function kindFor(string $extension, string $sniffed): ?string
    {
        foreach (self::TYPES as $kind => $type) {
            if (!in_array($extension, $type['extensions'], true)) {
                continue;
            }
            if (isset($type['mime_prefix'])) {
                return str_starts_with($sniffed, $type['mime_prefix']) ? $kind : null;
            }

            return in_array($sniffed, $type['mimes'], true) ? $kind : null;
        }

        return null;
    }

    public static function disk(): string
    {
        return (string) config('course_builder.disk', 'course_builder_private');
    }

    /** Converts the stored file and replaces the source's fragments. */
    public function ingest(Source $source): SourceDocument
    {
        $disk = Storage::disk(self::disk());
        $tmp = tempnam(sys_get_temp_dir(), 'cbsrc');
        file_put_contents($tmp, (string) $disk->get($source->path));
        try {
            $pageMarks = [];
            $meta = ['kind' => $source->kind()];
            switch ($source->kind()) {
                case 'pdf':
                    $pdf = (new PdfConverter())->convert($tmp, (int) config('course_builder.limits.pdf_pages', 300));
                    $markdown = $pdf['markdown'];
                    $pageMarks = $pdf['page_marks'];
                    $meta += ['pages' => $pdf['pages'], 'pdf_native' => $pdf['pdf_native'], 'title' => $pdf['title']];
                    break;
                case 'docx':
                    $docx = (new DocxConverter())->convert($tmp, (int) config('course_builder.limits.docx_uncompressed_bytes'));
                    $markdown = $docx['markdown'];
                    $meta['title'] = $docx['title'];
                    break;
                default:
                    $markdown = self::cleanMarkdown((string) file_get_contents($tmp));
            }
        } catch (UploadRejected $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        } finally {
            @unlink($tmp);
        }

        $split = (new Fragmenter((int) config('course_builder.fragments.min_tokens', 150), (int) config('course_builder.fragments.max_tokens', 600)))
            ->split($markdown, $pageMarks);
        if ($split['fragments'] === []) {
            throw new RuntimeException('No text could be extracted from this file.');
        }

        // ordinal within the heading path across the document (two sections with the same path never collide)
        $seen = [];
        $rows = [];
        foreach ($split['fragments'] as $fragment) {
            $pathKey = implode("\x1E", $fragment['heading_path']);
            $ordinal = $seen[$pathKey] = ($seen[$pathKey] ?? -1) + 1;
            $rows[] = $fragment + [
                'id' => FragmentId::make($source->id, $fragment['heading_path'], $ordinal),
                'source_id' => $source->id,
                'content_hash' => hash('sha256', $fragment['text']),
                'path_ordinal' => $ordinal,
            ];
        }

        $tokens = array_sum(array_column($rows, 'token_estimate'));
        $title = $split['title'] ?? ($meta['title'] ?? null) ?: pathinfo($source->original_name, PATHINFO_FILENAME);
        $meta += [
            'title' => $title,
            'sections' => $split['sections'],
            'fragments' => count($rows),
            'words' => str_word_count(strip_tags($markdown)),
            'language' => self::guessLanguage($markdown),
        ];
        $meta['title'] = $title;

        $markdownPath = $source->path . '.md';
        $disk->put($markdownPath, $markdown);

        DB::transaction(function () use ($source, $rows, $tokens, $meta, $markdownPath) {
            Fragment::query()->where('source_id', $source->id)->delete();
            foreach ($rows as $i => $row) {
                Fragment::query()->create([
                    'id' => $row['id'],
                    'source_id' => $row['source_id'],
                    'ordinal' => $i,
                    'heading_path' => $row['heading_path'],
                    'section' => $row['section'],
                    'level' => $row['level'],
                    'text' => $row['text'],
                    'char_start' => $row['char_start'],
                    'char_end' => $row['char_end'],
                    'page_start' => $row['page_start'],
                    'page_end' => $row['page_end'],
                    'token_estimate' => $row['token_estimate'],
                    'content_hash' => $row['content_hash'],
                ]);
            }
            $source->forceFill([
                'status' => 'ready',
                'markdown_path' => $markdownPath,
                'metadata' => $meta,
                'token_estimate' => $tokens,
                'error' => null,
            ])->save();
        });

        return new SourceDocument($markdown, $rows, $meta);
    }

    public static function cleanMarkdown(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
        // front matter is metadata, not content
        $text = (string) preg_replace('/\A---\n.*?\n---\n/s', '', $text);
        $text = (string) preg_replace('/<!--.*?-->/s', '', $text);
        $text = (string) preg_replace('/[ \t]+$/m', '', $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text) . "\n";
    }

    /** A cheap language guess from stop words (en, pl, de, es, fr); the brief can override it. */
    public static function guessLanguage(string $text): string
    {
        $sample = ' ' . mb_strtolower(mb_substr($text, 0, 20000)) . ' ';
        $words = [
            'en' => [' the ', ' and ', ' of ', ' is ', ' to '],
            'pl' => [' i ', ' w ', ' nie ', ' się ', ' jest '],
            'de' => [' der ', ' und ', ' die ', ' ist ', ' nicht '],
            'es' => [' el ', ' la ', ' de ', ' que ', ' y '],
            'fr' => [' le ', ' la ', ' et ', ' les ', ' est '],
        ];
        $scores = [];
        foreach ($words as $lang => $list) {
            $scores[$lang] = array_sum(array_map(fn ($w) => substr_count($sample, $w), $list));
        }
        arsort($scores);

        return (string) array_key_first($scores);
    }
}
