<?php

namespace Tests\Integrations;

use Tests\TestCase;

/**
 * No server-side code fetches a public storage URL (docs/plans/tech-debt-and-quality-bar.md TD-3).
 *
 * `Storage::url()` and the disk `url` give the address a browser uses (http://storage.localhost/..., a CDN, a
 * tenant host). Inside a container that host name does not resolve to the object store, so a server-side
 * `Http::get()`, `file_get_contents()` or `fopen()` on such a value fails in Docker and silently depends on the
 * public network elsewhere. Read through the disk (`Storage::get()`, `readStream()`) or the internal endpoint.
 *
 * The scan is a heuristic on the source text of `packages/<name>/src`: a call to one of those readers whose
 * argument is a URL builder, or a variable assigned from one earlier in the same file. It names the file.
 */
class NoPublicStorageFetchTest extends TestCase
{
    /** file (relative to api/) => why this read is fine. Keep empty: change the code instead. */
    private const ALLOWED = [];

    /** Readers that fetch whatever they are given. */
    private const READERS = '(?:Http::[^;]*?->get|Http::get|(?<![>:\\w])(?:file_get_contents|fopen|readfile|copy))\\s*\\(';

    /** Expressions that produce a public URL. */
    private const URL_SOURCES = '(?:Storage::(?:disk\([^)]*\)->)?(?:cloud\(\)->)?url\(|\$\w*[dD]isk\w*->url\(|(?<![>:\w])url\(|(?<![>:\w])asset\(|AWS_URL|config\(\s*[\'"]app\.url[\'"])';

    public function testNoPackageReadsAPublicStorageUrlOnTheServer(): void
    {
        $found = [];
        $root = dirname(__DIR__, 2);
        $files = [];
        foreach (glob($root . '/packages/*/src', GLOB_ONLYDIR) ?: [] as $source) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);
        foreach ($files as $file) {
            $relative = ltrim(str_replace($root, '', $file), '/');
            if (isset(self::ALLOWED[$relative])) {
                continue;
            }
            foreach ($this->violations((string) file_get_contents($file)) as $line) {
                $found[] = "{$relative}: {$line}";
            }
        }

        $this->assertSame([], $found, "Server-side reads of a public storage URL (read through the disk instead, see this test):\n" . implode("\n", $found));
    }

    public function testTheScannerFlagsTheShapesItIsMeantFor(): void
    {
        $this->assertNotSame([], $this->violations('<?php $x = file_get_contents(Storage::url($path));'));
        $this->assertNotSame([], $this->violations('<?php $url = Storage::disk("s3")->url($p); $r = Http::get($url);'));
        $this->assertNotSame([], $this->violations('<?php $url = url(Storage::url($p)); $h = fopen($url, "r");'));
        $this->assertNotSame([], $this->violations('<?php $u = $disk->url($p); copy($u, $to);'));
        $this->assertNotSame([], $this->violations('<?php $r = Http::timeout(5)->get(asset($p));'));
    }

    public function testTheScannerLeavesLocalAndServiceReadsAlone(): void
    {
        $this->assertSame([], $this->violations('<?php $r = file_get_contents(__DIR__ . "/schema.json"); $s = fopen($path, "r");'));
        $this->assertSame([], $this->violations('<?php $url = Storage::url($p); return ["url" => $url];'));
        $this->assertSame([], $this->violations('<?php $r = $this->request()->get($this->url("libraries")); $d = Storage::get($p);'));
        $this->assertSame([], $this->violations('<?php $u = Storage::url($a); $r = file_get_contents($other);'));
    }

    /** @return list<string> */
    private function violations(string $source): array
    {
        $found = [];
        $lines = preg_split('/\R/', $source) ?: [];

        // variables that hold a public URL
        $tainted = [];
        foreach ($lines as $line) {
            if (preg_match('/(\$\w+)\s*=\s*[^;]*' . self::URL_SOURCES . '/', $line, $m)) {
                $tainted[$m[1]] = true;
            }
        }

        foreach ($lines as $number => $line) {
            if (!preg_match('/' . self::READERS . '(?<arg>.*)$/', $line, $m)) {
                continue;
            }
            $argument = $m['arg'];
            $direct = preg_match('/' . self::URL_SOURCES . '/', $argument) === 1;
            $viaVariable = false;
            foreach (array_keys($tainted) as $variable) {
                if (preg_match('/' . preg_quote($variable, '/') . '\b/', $argument)) {
                    $viaVariable = true;
                    break;
                }
            }
            if ($direct || $viaVariable) {
                $found[] = 'line ' . ($number + 1) . ': ' . trim($line);
            }
        }

        return $found;
    }
}
