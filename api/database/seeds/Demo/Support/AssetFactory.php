<?php

namespace Database\Seeders\Demo\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Produces the demo media at seed time, so the repository only keeps small
 * templates: PNG illustrations (GD), PDFs (dompdf), audio and video (ffmpeg)
 * and zipped SCORM / cmi5 packages built from the HTML in Demo/assets.
 *
 * Generated files are written to storage/app/demo-assets/<experience> and
 * reused on the next run unless DEMO_REFRESH_ASSETS is set.
 */
class AssetFactory
{
    private string $experience;
    private bool $refresh;

    public function __construct(string $experience)
    {
        $this->experience = $experience;
        $this->refresh = filter_var(env('DEMO_REFRESH_ASSETS', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function assetsPath(string $path = ''): string
    {
        return __DIR__ . '/../assets' . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    public function path(string $name): string
    {
        $dir = storage_path('app/demo-assets/' . $this->experience);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir . '/' . $name;
    }

    public function exists(string $name): bool
    {
        return !$this->refresh && is_file($this->path($name)) && filesize($this->path($name)) > 0;
    }

    /**
     * @param callable(): Canvas $draw
     */
    public function image(string $name, callable $draw): string
    {
        $path = $this->path($name);
        if ($this->exists($name)) {
            return $path;
        }
        $canvas = $draw();
        str_ends_with($name, '.jpg') ? $canvas->saveJpeg($path) : $canvas->savePng($path);

        return $path;
    }

    public function pdf(string $name, string $html, string $orientation = 'portrait'): string
    {
        $path = $this->path($name);
        if ($this->exists($name)) {
            return $path;
        }
        Pdf::loadHTML($html)->setPaper('a4', $orientation)->setOption('defaultFont', 'DejaVu Sans')->setOption('isFontSubsettingEnabled', true)->save($path);

        return $path;
    }

    /**
     * Synthesises an mp3. $expression is an ffmpeg aevalsrc expression of t;
     * $filters an optional audio filter chain (fades, echo, low-pass).
     */
    public function audio(string $name, int $seconds, string $expression, string $filters = ''): string
    {
        $path = $this->path($name);
        if ($this->exists($name)) {
            return $path;
        }
        $chain = 'afade=t=in:d=2,afade=t=out:st=' . max(0, $seconds - 3) . ':d=3' . ($filters !== '' ? ',' . $filters : '');
        $this->ffmpeg([
            '-f', 'lavfi', '-i', 'aevalsrc=' . str_replace(',', '\\,', $expression) . ':s=22050:d=' . $seconds,
            '-af', $chain,
            '-ac', '1', '-c:a', 'libmp3lame', '-b:a', '48k', $path,
        ]);

        return $path;
    }

    /**
     * Builds an mp4 slideshow from still frames with a slow zoom, cross-fades
     * and an optional soundtrack (an mp3 produced by audio()).
     *
     * @param array<int, string> $frames PNG/JPEG paths, 960x540 recommended
     */
    public function video(string $name, array $frames, int $secondsPerFrame, ?string $soundtrack = null): string
    {
        $path = $this->path($name);
        if ($this->exists($name)) {
            return $path;
        }
        $fps = 24;
        $frameCount = $secondsPerFrame * $fps;
        $args = [];
        foreach ($frames as $frame) {
            array_push($args, '-loop', '1', '-t', (string) $secondsPerFrame, '-i', $frame);
        }
        if ($soundtrack) {
            array_push($args, '-i', $soundtrack);
        }
        $filters = [];
        foreach (array_keys($frames) as $i) {
            $filters[] = "[{$i}:v]scale=1920:1080,zoompan=z='min(zoom+0.0006,1.08)':d={$frameCount}:s=960x540:fps={$fps},format=yuv420p,setsar=1[v{$i}]";
        }
        $last = 'v0';
        $fade = 1;
        foreach (array_slice(array_keys($frames), 1) as $i) {
            $offset = $i * ($secondsPerFrame - $fade);
            $filters[] = "[{$last}][v{$i}]xfade=transition=fade:duration={$fade}:offset={$offset}[x{$i}]";
            $last = "x$i";
        }
        $total = count($frames) * $secondsPerFrame - (count($frames) - 1) * $fade;
        array_push($args, '-filter_complex', implode(';', $filters), '-map', "[{$last}]");
        if ($soundtrack) {
            array_push($args, '-map', count($frames) . ':a', '-c:a', 'aac', '-b:a', '64k', '-shortest');
        }
        array_push($args, '-t', (string) $total, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '30', '-movflags', '+faststart', $path);
        $this->ffmpeg($args);

        return $path;
    }

    /**
     * Zips a template folder from Demo/assets, replacing {{key}} placeholders
     * in text files.
     *
     * @param array<string, string> $replacements
     * @param array<string, string> $extraFiles path inside the zip => local file
     */
    public function zipTemplate(string $name, string $templateDir, array $replacements = [], array $extraFiles = []): string
    {
        $path = $this->path($name);
        if ($this->exists($name)) {
            return $path;
        }
        $source = self::assetsPath($templateDir);
        if (!is_dir($source)) {
            throw new RuntimeException("Missing template folder $source");
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create $path");
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $relative = ltrim(substr($file->getPathname(), strlen($source)), '/');
            $content = (string) file_get_contents($file->getPathname());
            if (preg_match('/\.(html|xml|js|css|json)$/', $relative)) {
                foreach ($replacements as $key => $value) {
                    $content = str_replace('{{' . $key . '}}', $value, $content);
                }
            }
            $zip->addFromString($relative, $content);
        }
        foreach ($extraFiles as $inside => $local) {
            $zip->addFile($local, $inside);
        }
        $zip->close();

        return $path;
    }

    public function upload(string $path, ?string $name = null): UploadedFile
    {
        return new UploadedFile($path, $name ?? basename($path), null, null, true);
    }

    /** @param array<int, string> $args */
    private function ffmpeg(array $args): void
    {
        $process = new Process(array_merge(['ffmpeg', '-y', '-hide_banner', '-loglevel', 'error'], $args));
        $process->setTimeout(300);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException('ffmpeg failed: ' . $process->getErrorOutput());
        }
    }
}
