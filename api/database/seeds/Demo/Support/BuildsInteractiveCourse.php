<?php

namespace Database\Seeders\Demo\Support;

use RuntimeException;
use Ulams\TopicTypeGift\Services\Contracts\GiftQuestionServiceContract;

/**
 * The program of an interactive demo course, read from `Demo/content/<contentDir>/modules/*.md`
 * ({@see ModuleFile}): one file per lesson, in file name order. Used by the gravity and poland
 * experiences; each module has Interactive topics pinned to a step range of the package, a short
 * explanation, a Layout topic and a GIFT quiz, and the course ends with a final test and a
 * "Sources and licence" lesson.
 *
 * The experience supplies {@see packageName()} and {@see contentDir()}.
 */
trait BuildsInteractiveCourse
{
    private ?Sources $sourcesCache = null;
    /** @var array<string, int> library id of each package, by name */
    private array $packageIds = [];

    /** Name of the interactive package (a key of ContentPackages::TITLES). */
    abstract protected function packageName(): string;

    /**
     * Every package the course may use. An Interactive block names its package with `package=`, and without it
     * the course's {@see packageName()} is used.
     *
     * @return array<int, string>
     */
    protected function packageNames(): array
    {
        return [$this->packageName()];
    }

    /** Folder below Demo/content that holds modules/ (and the sources file when it is not shared). */
    abstract protected function contentDir(): string;

    /** Folder (below Demo/content) of sources.json. */
    protected function sourcesDir(): string
    {
        return $this->key();
    }

    protected function contentRoot(): string
    {
        return dirname(__DIR__) . '/content';
    }

    public function sources(): Sources
    {
        return $this->sourcesCache ??= new Sources($this->contentRoot() . '/' . $this->sourcesDir() . '/sources.json');
    }

    /** @return array<int, string> paths of the module files, in order */
    public function moduleFiles(): array
    {
        $files = glob($this->contentRoot() . '/' . $this->contentDir() . '/modules/*.md') ?: [];
        sort($files);

        return $files;
    }

    private function interactivePackageId(?string $name = null): int
    {
        $name ??= $this->packageName();

        return $this->packageIds[$name] ??= ContentPackages::package($name, auth()->id())->getKey();
    }

    /**
     * The `{{asset:name.webp}}` images of a text: files of Demo/assets/<key>/images, stored with the course and
     * replaced by their public URL.
     */
    private function withAssets(string $text): string
    {
        return (string) preg_replace_callback('/\{\{asset:([\w.-]+)\}\}/', function (array $m): string {
            $path = AssetFactory::assetsPath($this->key() . '/images/' . $m[1]);
            if (!is_file($path)) {
                throw new RuntimeException("Unknown asset {$m[1]} ($path)");
            }

            return $this->publicUrl($path);
        }, $text);
    }

    /**
     * @return array<int, array<string, mixed>> lessons for DemoExperience::buildProgram()
     */
    protected function modulesProgram(): array
    {
        $stepIds = [];
        foreach ($this->packageNames() as $name) {
            $this->interactivePackageId($name); // fails the run loudly when a zip is missing
            $stepIds[$name] = ContentPackages::stepIds($name);
        }
        $modules = [];
        foreach ($this->moduleFiles() as $file) {
            $modules[] = ModuleFile::parse((string) file_get_contents($file), basename($file)) + ['file' => basename($file)];
        }

        $lessons = [];
        foreach ($modules as $module) {
            $meta = $module['meta'];
            $topics = [];
            foreach ($module['blocks'] as $block) {
                $topics[] = $this->topicSpec($block, $stepIds, $module['file']);
            }
            $lessons[] = [
                'title' => $meta['title'] ?? throw new RuntimeException($module['file'] . ': title is missing'),
                'summary' => $meta['summary'] ?? null,
                'duration' => $meta['duration'] ?? null,
                'topics' => $topics,
                'cited' => $this->citedIn($module['blocks']),
            ];
        }

        // the last lesson lists every source the course cites, module by module
        foreach ($lessons as $i => $lesson) {
            foreach ($lesson['topics'] as $j => $topic) {
                if (isset($topic['placeholder']) && $topic['placeholder'] === 'all-sources') {
                    $lessons[$i]['topics'][$j] = $this->allSourcesTopic($topic, $lessons);
                }
            }
        }
        foreach ($lessons as $i => $lesson) {
            unset($lessons[$i]['cited']);
        }

        return $lessons;
    }

    /** @return array<int, string> */
    private function citedIn(array $blocks): array
    {
        $ids = [];
        foreach ($blocks as $block) {
            $text = $block['body'] . ' ' . ($block['attrs']['sources'] ?? '');
            foreach ($this->sources()->cited($text) as $id) {
                $ids[$id] = true;
            }
            foreach (array_filter(array_map('trim', explode(',', $block['attrs']['sources'] ?? ''))) as $id) {
                if (!$this->sources()->has($id)) {
                    throw new RuntimeException("Unknown source id $id");
                }
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param array{kind: string, attrs: array<string, string>, body: string} $block
     * @param array<string, array<int, string>> $stepIds step ids of each package, by package name
     * @return array<string, mixed>
     */
    private function topicSpec(array $block, array $stepIds, string $file): array
    {
        $a = $block['attrs'];
        $title = $a['title'] ?? throw new RuntimeException("$file: a {$block['kind']} block has no title");
        $common = array_filter([
            'title' => $title,
            'duration' => $a['duration'] ?? null,
            'summary' => $a['summary'] ?? null,
            'introduction' => $a['intro'] ?? null,
            'preview' => isset($a['preview']) ? true : null,
            'can_skip' => isset($a['skip']) ? true : null,
        ], fn ($v) => $v !== null);

        switch ($block['kind']) {
            case 'richtext':
                if (trim($block['body']) === '{{sources:all}}' || str_contains($block['body'], '{{sources:all}}')) {
                    return $common + ['type' => 'richtext', 'placeholder' => 'all-sources', 'body' => $block['body']];
                }

                return $common + ['type' => 'richtext', 'make' => fn () => ['fields' => ['value' => $this->withAssets($this->sources()->cite($block['body']))], 'files' => []]];

            case 'interactive':
                $package = $a['package'] ?? $this->packageName();
                if (!isset($stepIds[$package])) {
                    throw new RuntimeException("$file: interactive \"$title\" names the unknown package $package");
                }
                $start = $a['start'] ?? throw new RuntimeException("$file: interactive \"$title\" has no start");
                $end = $a['end'] ?? $start;
                $from = array_search($start, $stepIds[$package], true);
                $to = array_search($end, $stepIds[$package], true);
                if ($from === false || $to === false || $from > $to) {
                    throw new RuntimeException("$file: interactive \"$title\": the steps $start..$end are not a range of the package $package");
                }
                $rule = $a['completion'] ?? 'on_range_end';
                if ($rule === 'on_score' && !isset($a['score'])) {
                    throw new RuntimeException("$file: interactive \"$title\" completes on a score but has no score=");
                }

                return $common + ['type' => 'interactive', 'make' => fn () => ['fields' => [
                    'value' => $this->interactivePackageId($package),
                    'start_step' => $start,
                    'end_step' => $end,
                    'completion_rule' => $rule,
                    'display' => $a['display'] ?? 'background',
                    'height' => (int) ($a['height'] ?? 640),
                    'text' => $this->sources()->cite($block['body'], true),
                ] + (isset($a['score']) ? ['pass_score' => (int) $a['score']] : []), 'files' => []]];

            case 'layout':
                $decoded = json_decode($block['body'], true);
                if (!is_array($decoded) || !is_array($decoded['document'] ?? null) || !is_string($decoded['fallback'] ?? null)) {
                    throw new RuntimeException("$file: layout \"$title\" needs JSON with \"document\" and \"fallback\" (" . json_last_error_msg() . ')');
                }

                return $common + ['type' => 'layout', 'make' => fn () => ['fields' => $this->layoutFields($decoded, $a), 'files' => []]];

            case 'quiz':
                $questions = [];
                $service = app(GiftQuestionServiceContract::class);
                foreach (ModuleFile::giftQuestions($block['body']) as $gift) {
                    $questions[] = ['type' => $service->getType($gift), 'gift' => $gift];
                }
                $count = count($questions);
                if ($count === 0) {
                    throw new RuntimeException("$file: quiz \"$title\" has no questions");
                }
                $pass = (int) ($a['pass'] ?? 60);

                return $common + ['type' => 'gift', 'questions' => $questions, 'fields' => [
                    'value' => $a['text'] ?? 'Check what you have learned. Each question is worth one point; you can try again.',
                    'max_attempts' => (int) ($a['attempts'] ?? 3),
                    'min_pass_score' => (int) ceil($count * $pass / 100),
                    'counts_to_grade' => true,
                    'weight' => (int) ($a['weight'] ?? 1),
                    'randomize_order' => false,
                ] + (isset($a['minutes']) ? ['max_execution_time' => (int) $a['minutes']] : [])];
        }
        throw new RuntimeException("$file: unknown block {$block['kind']}");
    }

    /**
     * The fields of a Layout topic: the document with a final "Sources" callout, and the Markdown fallback
     * with the numbered list.
     *
     * @param array<string, mixed> $decoded
     * @param array<string, string> $attrs
     * @return array<string, mixed>
     */
    private function layoutFields(array $decoded, array $attrs): array
    {
        $ids = array_values(array_filter(array_map('trim', explode(',', $attrs['sources'] ?? ''))));
        $document = $decoded['document'];
        $fallback = (string) $decoded['fallback'];
        if ($ids !== []) {
            $document[] = ['component' => 'Callout', 'props' => [
                'tone' => 'tip',
                'title' => 'Sources',
                'text' => mb_strimwidth($this->sources()->names($ids), 0, 1000, '…'),
            ]];
            $fallback = $this->sources()->cite($fallback . ' ' . implode(' ', array_map(fn ($id) => "{{src:$id}}", $ids)));
        }

        return ['document' => $document, 'markdown_fallback' => $fallback, 'schema_version' => '1'];
    }

    /**
     * @param array<string, mixed> $topic
     * @param array<int, array<string, mixed>> $lessons
     * @return array<string, mixed>
     */
    private function allSourcesTopic(array $topic, array $lessons): array
    {
        $sections = [];
        foreach ($lessons as $lesson) {
            if ($lesson['cited'] === []) {
                continue;
            }
            $lines = [];
            foreach ($lesson['cited'] as $id) {
                foreach ($this->sources()->references($id) as [$title, $url]) {
                    $lines[$url . $title] = '- ' . $title . ($url !== '' ? ', <' . $url . '>' : '');
                }
            }
            $sections[] = '### ' . $lesson['title'] . "\n\n" . implode("\n", $lines);
        }
        $body = str_replace('{{sources:all}}', implode("\n\n", $sections), $topic['body']);
        unset($topic['placeholder'], $topic['body']);

        return $topic + ['make' => fn () => ['fields' => ['value' => trim($body) . "\n"], 'files' => []]];
    }
}
