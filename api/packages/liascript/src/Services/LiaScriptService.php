<?php

namespace Ulams\LiaScript\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;
use Ulams\LiaScript\Models\LiaScriptDocument;
use Ulams\LiaScript\Models\LiaScriptVersion;
use Ulams\Uploads\Exceptions\UploadRejected;
use Ulams\Uploads\UploadGuard;
use Ulams\Uploads\Zip\SafeExtractor;

/**
 * LiaScript sources: Markdown plus assets, versioned. Every change, upload or restore adds an
 * immutable version; assets live on the default disk under liascript/<document>/v<version>/ and
 * are carried over by later versions that only change the text.
 */
class LiaScriptService
{
    public function __construct(
        private readonly UploadGuard $guard,
        private readonly SafeExtractor $extractor,
    ) {
    }

    public function create(?string $title, ?string $markdown, ?UploadedFile $file, ?int $authorId): LiaScriptDocument
    {
        return DB::transaction(function () use ($title, $markdown, $file, $authorId) {
            $document = LiaScriptDocument::query()->create([
                'title' => 'Untitled',
                'current_version' => 0,
                'author_id' => $authorId,
            ]);
            $version = $this->addVersion($document, $markdown, $file, [], null, $authorId);
            $document->update(['title' => $this->title($title, $version->markdown)]);

            return $document->refresh();
        });
    }

    public function update(LiaScriptDocument $document, ?string $title, ?string $markdown, ?UploadedFile $file, ?string $note, ?int $authorId): LiaScriptDocument
    {
        return DB::transaction(function () use ($document, $title, $markdown, $file, $note, $authorId) {
            /** @var LiaScriptDocument $locked */
            $locked = LiaScriptDocument::query()->lockForUpdate()->findOrFail($document->getKey());
            if ($markdown !== null || $file !== null) {
                $previous = $locked->version($locked->current_version);
                $this->addVersion($locked, $markdown, $file, $previous?->assets ?? [], $note, $authorId);
            }
            if ($title !== null && $title !== '') {
                $locked->update(['title' => mb_substr($title, 0, 255)]);
            }

            return $locked->refresh();
        });
    }

    public function restore(LiaScriptDocument $document, int $version, ?int $authorId): LiaScriptDocument
    {
        return DB::transaction(function () use ($document, $version, $authorId) {
            /** @var LiaScriptDocument $locked */
            $locked = LiaScriptDocument::query()->lockForUpdate()->findOrFail($document->getKey());
            $source = $locked->version($version);
            if ($source === null) {
                throw ValidationException::withMessages(['version' => "Version {$version} does not exist."]);
            }
            $next = $locked->current_version + 1;
            LiaScriptVersion::query()->create([
                'liascript_document_id' => $locked->getKey(),
                'version' => $next,
                'markdown' => $source->markdown,
                'assets' => $source->assets,
                'change_note' => "Restored version {$version}",
                'author_id' => $authorId,
                'restored_from' => $version,
            ]);
            $locked->update(['current_version' => $next]);

            return $locked->refresh();
        });
    }

    public function source(LiaScriptDocument $document, ?int $version = null): LiaScriptVersion
    {
        $found = $document->version($version ?? $document->current_version);
        if ($found === null) {
            throw ValidationException::withMessages(['version' => 'This version does not exist.']);
        }

        return $found;
    }

    public function delete(LiaScriptDocument $document): void
    {
        $directory = 'liascript/' . $document->getKey();
        $document->delete();
        $this->disk()->deleteDirectory($directory);
    }

    /**
     * Remote `import:` macros run code from elsewhere; they are blocked on the content origin.
     *
     * @return string[]
     */
    public function warnings(string $markdown): array
    {
        preg_match_all('/^\s*import:\s*(https?:\/\/\S+)/mi', $markdown, $matches);

        return array_map(
            fn (string $url) => "Remote import {$url} will not load: courses are served without access to other sites. Add the macros to the course instead.",
            array_values(array_unique($matches[1]))
        );
    }

    private function addVersion(LiaScriptDocument $document, ?string $markdown, ?UploadedFile $file, array $assets, ?string $note, ?int $authorId): LiaScriptVersion
    {
        $next = $document->current_version + 1;

        if ($file !== null) {
            [$markdown, $uploaded] = $this->fromUpload($file, $document, $next);
            $assets = $uploaded + $assets;
        }
        $markdown = $this->validMarkdown((string) $markdown);

        $version = LiaScriptVersion::query()->create([
            'liascript_document_id' => $document->getKey(),
            'version' => $next,
            'markdown' => $markdown,
            'assets' => $assets,
            'change_note' => $note === null ? null : mb_substr($note, 0, 500),
            'author_id' => $authorId,
        ]);
        $document->update(['current_version' => $next]);

        return $version;
    }

    /**
     * @return array{0: string, 1: array<string, array{path: string, size: int, sha256: string}>}
     */
    private function fromUpload(UploadedFile $file, LiaScriptDocument $document, int $version): array
    {
        try {
            $this->guard->check($file, 'liascript');
        } catch (UploadRejected $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        if (strtolower($file->getClientOriginalExtension()) !== 'zip') {
            return [(string) file_get_contents((string) $file->getRealPath()), []];
        }

        $prefix = "liascript/{$document->getKey()}/v{$version}";
        $disk = $this->disk();
        try {
            $written = $this->extractor->extractToDisk((string) $file->getRealPath(), $disk, $prefix, $this->guard->limitsFor('liascript'));
        } catch (UploadRejected $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        $relative = array_map(fn (string $path) => substr($path, strlen($prefix) + 1), $written);
        $markdownFile = in_array('README.md', $relative, true)
            ? 'README.md'
            : collect($relative)->filter(fn ($p) => !str_contains($p, '/') && str_ends_with(strtolower($p), '.md'))->sort()->first();
        if ($markdownFile === null) {
            $disk->deleteDirectory($prefix);
            throw ValidationException::withMessages(['file' => 'The archive needs a README.md (or another .md file) at its root.']);
        }

        try {
            $markdown = $this->validMarkdown((string) $disk->get($prefix . '/' . $markdownFile));
            $disk->delete($prefix . '/' . $markdownFile);
            $assets = [];
            foreach ($relative as $path) {
                if ($path === $markdownFile) {
                    continue;
                }
                $stored = $prefix . '/' . $path;
                $assets[$path] = [
                    'path' => $stored,
                    'size' => (int) $disk->size($stored),
                    'sha256' => hash('sha256', (string) $disk->get($stored)),
                ];
            }
        } catch (Throwable $e) {
            $disk->deleteDirectory($prefix);
            throw $e;
        }

        return [$markdown, $assets];
    }

    private function validMarkdown(string $markdown): string
    {
        $max = (int) config('ulams_liascript.max_markdown_bytes', 2 * 1024 * 1024);
        if (trim($markdown) === '') {
            throw ValidationException::withMessages(['markdown' => 'The course text is empty.']);
        }
        if (strlen($markdown) > $max || !mb_check_encoding($markdown, 'UTF-8') || str_contains($markdown, "\0")) {
            throw ValidationException::withMessages(['markdown' => 'The course text must be UTF-8 Markdown of at most ' . intdiv($max, 1024) . ' KB.']);
        }

        return str_replace("\r\n", "\n", $markdown);
    }

    private function title(?string $title, string $markdown): string
    {
        if ($title !== null && trim($title) !== '') {
            return mb_substr(trim($title), 0, 255);
        }
        if (preg_match('/^#\s+(.+)$/m', $markdown, $m)) {
            return mb_substr(trim($m[1]), 0, 255);
        }

        return 'Untitled';
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('ulams_liascript.disk') ?: config('filesystems.default'));
    }
}
