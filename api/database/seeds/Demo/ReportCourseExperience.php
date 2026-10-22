<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Support\Canvas;
use RuntimeException;

/**
 * A free published reading course (rich text and GIFT only) rebuilt on every demo reset
 * from database/seeds/Demo/content/reports/{key}.json. One academy, one course.
 */
abstract class ReportCourseExperience extends DemoExperience
{
    /** @var array<string, mixed>|null */
    private ?array $spec = null;

    /**
     * Cover line, palette and the tutor, categories and tags around the course.
     *
     * @return array{
     *     kicker: string,
     *     cover: string,
     *     certificate: string,
     *     palette: array<string, string>,
     *     people: array<string, array<string, string>>,
     *     categories: array<int, array{parent: ?string, name: string}>,
     *     tags: array<int, string>
     * }
     */
    abstract protected function academy(): array;

    /** @return array<string, mixed> */
    protected function spec(): array
    {
        if ($this->spec === null) {
            $path = __DIR__ . '/content/reports/' . $this->key() . '.json';
            $decoded = json_decode((string) file_get_contents($path), true);
            if (!is_array($decoded) || !isset($decoded['lessons'])) {
                throw new RuntimeException('Cannot read report course ' . $path);
            }
            $this->spec = $decoded;
        }

        return $this->spec;
    }

    protected function courseFields(): array
    {
        $spec = $this->spec();

        return [
            'title' => $spec['title'],
            'subtitle' => $spec['subtitle'],
            'summary' => $spec['summary'],
            'description' => $spec['description'],
            'level' => ucfirst((string) ($spec['level'] ?: 'beginner')),
            'language' => $spec['language'] ?: 'en',
            'duration' => $spec['duration'] ?: 'Self-paced',
            'hours_to_complete' => null,
            'target_group' => mb_substr((string) ($spec['target_group'] ?: ''), 0, 100),
            'public' => true,
        ];
    }

    protected function people(): array
    {
        return $this->academy()['people'];
    }

    protected function categories(): array
    {
        return $this->academy()['categories'];
    }

    protected function tags(): array
    {
        return $this->academy()['tags'];
    }

    protected function palette(): array
    {
        return $this->academy()['palette'];
    }

    protected function certificateName(): string
    {
        return $this->academy()['certificate'];
    }

    protected function commerce(): void
    {
    }

    protected function courseMedia(): array
    {
        $draw = function (int $width, int $height): Canvas {
            $palette = $this->palette();
            $canvas = new Canvas($width, $height, $palette['background']);
            $canvas->centeredText($this->academy()['kicker'], $width / 2, $height * 0.32, 28, $palette['accent'], 'sans', true);
            $canvas->paragraph(
                $this->academy()['cover'],
                $width * 0.12,
                $height * 0.46,
                $width * 0.76,
                $width >= 1500 ? 54 : 42,
                $palette['text'],
                'sans',
                true,
                1.25
            );

            return $canvas;
        };

        return [
            'image' => $this->assets->image('course-image.png', fn () => $draw(1600, 900)),
            'poster' => $this->assets->image('course-poster.png', fn () => $draw(1280, 720)),
            'teaser' => null,
        ];
    }

    protected function program(): array
    {
        $lessons = [];
        foreach ($this->spec()['lessons'] as $lesson) {
            $topics = [];
            foreach ($lesson['topics'] as $topic) {
                $topics[] = $this->topic($topic);
            }
            $lessons[] = array_filter([
                'title' => $lesson['title'],
                'summary' => $lesson['summary'] ?? null,
                'duration' => $lesson['duration'] ?? null,
                'topics' => $topics,
            ], fn ($value) => $value !== null && $value !== '');
        }

        return $lessons;
    }

    /** @param array<string, mixed> $topic */
    private function topic(array $topic): array
    {
        $spec = array_filter([
            'type' => $topic['type'],
            'title' => $topic['title'],
            'summary' => $topic['summary'] ?? null,
            'introduction' => $topic['introduction'] ?? null,
            'description' => $topic['description'] ?? null,
            'duration' => $topic['duration'] ?? null,
            'preview' => !empty($topic['preview']) ? true : null,
            'fields' => $this->fields($topic['fields'] ?? []),
        ], fn ($value) => $value !== null && $value !== '');
        if ($topic['type'] === 'gift') {
            $spec['questions'] = array_map(fn (array $question) => [
                'type' => $question['type'],
                'gift' => $question['gift'],
                'score' => $question['score'] ?? 1,
            ], $topic['questions'] ?? []);
        }

        return $spec;
    }

    /** @param array<string, mixed> $fields */
    private function fields(array $fields): array
    {
        return array_filter($fields, fn ($value) => $value !== null);
    }
}
