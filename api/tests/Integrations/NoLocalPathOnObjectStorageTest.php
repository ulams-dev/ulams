<?php

namespace Tests\Integrations;

use Tests\TestCase;

/**
 * No server-side code treats `Storage::path()` as a place on this machine for the default disk
 * (docs/plans/phase-8-self-hosting.md 4.3, ADR 0041).
 *
 * On a local disk `path()` is an absolute file name. On S3 it is only the object key, so `parseFile(Storage::path($p))`,
 * `getimagesize(Storage::path($p))`, `File::cleanDirectory(Storage::path($p))` or an optimiser run on it fail or, worse, act on
 * a relative path in the working directory. Read through the disk (`Storage::get()`, `readStream()`, `deleteDirectory()`) or,
 * for a library that needs a file, `Ulams\Core\Storage\LocalCopy::with()`. A disk named `local` or `testing` is local on purpose.
 *
 * The scan is a heuristic on the source text of `packages/<name>/src`: it names the file and the line.
 */
class NoLocalPathOnObjectStorageTest extends TestCase
{
    /** file (relative to api/) => why `path()` on the default disk is fine there. Keep empty: change the code instead. */
    private const ALLOWED = [];

    private const FLAGGED = '(?:Storage::path\\(|Storage::disk\\(\\s*\\)->path\\(|Storage::disk\\(\\s*(?![\'"](?:local|testing)[\'"])[^)]+\\)->path\\()';

    public function testNoPackagePassesAnObjectKeyOffAsAFilePath(): void
    {
        $found = [];
        $root = dirname(__DIR__, 2);
        foreach (glob($root . '/packages/*/src', GLOB_ONLYDIR) ?: [] as $source) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $relative = ltrim(str_replace($root, '', $file->getPathname()), '/');
                if (isset(self::ALLOWED[$relative])) {
                    continue;
                }
                foreach ($this->violations((string) file_get_contents($file->getPathname())) as $line) {
                    $found[] = "{$relative}: {$line}";
                }
            }
        }
        sort($found);

        $this->assertSame([], $found, "Storage::path() on a disk that may be S3 (read through the disk or use LocalCopy::with(), see this test):\n" . implode("\n", $found));
    }

    public function testTheScannerFlagsTheShapesItIsMeantFor(): void
    {
        $this->assertNotSame([], $this->violations('<?php $p = Storage::path($file);'));
        $this->assertNotSame([], $this->violations('<?php $p = Storage::disk()->path($file);'));
        $this->assertNotSame([], $this->violations('<?php $p = Storage::disk($name)->path($file);'));
        $this->assertNotSame([], $this->violations('<?php File::cleanDirectory(Storage::path("course/1"));'));
    }

    public function testTheScannerLeavesLocalDisksAndDiskObjectsAlone(): void
    {
        $this->assertSame([], $this->violations('<?php $p = Storage::disk(\'local\')->path($file);'));
        $this->assertSame([], $this->violations('<?php $p = Storage::disk("testing")->path($file);'));
        $this->assertSame([], $this->violations('<?php $p = $request->path(); $q = $this->path();'));
        $this->assertSame([], $this->violations('<?php $p = Storage::get($file); Storage::deleteDirectory($dir);'));
        $this->assertSame([], $this->violations("<?php\n// Storage::path() is only the key on S3\n/** Storage::path(\$x) */\n * Storage::path()"));
    }

    /** @return list<string> */
    private function violations(string $source): array
    {
        $found = [];
        foreach (preg_split('/\R/', $source) ?: [] as $number => $line) {
            if (preg_match('#^\s*(//|\*|/\*|\#)#', $line)) {
                continue;
            }
            if (preg_match('/' . self::FLAGGED . '/', $line)) {
                $found[] = 'line ' . ($number + 1) . ': ' . trim($line);
            }
        }

        return $found;
    }
}
