<?php

namespace Ulams\CourseBuilder\ContentTypes;

use Throwable;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\H5P\Services\Contracts\H5PServiceClientContract;

/**
 * The H5P libraries the builder may create (ADR 0050): fill in the blanks, drag the words and dialog
 * cards. Per library there is a small JSON Schema of our own that the model fills
 * (`resources/schemas/h5p/<library>.json`) and a deterministic mapping to H5P `params`. The
 * allow-list is checked against the libraries the H5P service has installed, at runtime.
 */
final class H5pLibraries
{
    public const SUPPORTED = ['H5P.Blanks', 'H5P.DragText', 'H5P.Dialogcards'];

    /** @var array{at:int,libraries:array<string,string>}|null */
    private ?array $memo = null;

    public function __construct(private readonly H5PServiceClientContract $client)
    {
    }

    /**
     * Allowed libraries that are installed, machine name → uber name ("H5P.Blanks 1.14").
     *
     * @return array<string,string>
     */
    public function installed(): array
    {
        if ($this->memo !== null && $this->memo['at'] > time() - 60) {
            return $this->memo['libraries'];
        }
        $libraries = [];
        if (config('course_builder.content_types.h5p', true) !== false) {
            try {
                $libraries = array_intersect_key($this->client->libraries(), array_flip($this->allowed()));
            } catch (Throwable) {
                $libraries = [];
            }
        }
        $this->memo = ['at' => time(), 'libraries' => $libraries];

        return $libraries;
    }

    /** @return string[] */
    public function allowed(): array
    {
        $configured = array_values(array_filter((array) config('course_builder.h5p_libraries', self::SUPPORTED), fn ($l) => in_array($l, self::SUPPORTED, true)));

        return $configured === [] ? self::SUPPORTED : $configured;
    }

    /** @return array<string,mixed> */
    public static function schema(string $library): array
    {
        $file = __DIR__ . '/../../resources/schemas/h5p/' . $library . '.json';
        if (!in_array($library, self::SUPPORTED, true) || !is_file($file)) {
            throw new \InvalidArgumentException("Unsupported H5P library {$library}");
        }

        return (array) json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Output schema of the interaction task: one choice per installed library, each with its own data schema.
     *
     * @param string[] $libraries machine names
     * @return array<string,mixed>
     */
    public static function outputSchema(array $libraries): array
    {
        $choices = [];
        foreach ($libraries as $library) {
            $choices[] = [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['library', 'title', 'citations', 'objectiveIds', 'data'],
                'properties' => [
                    'library' => ['type' => 'string', 'enum' => [$library]],
                    'title' => ['type' => 'string', 'description' => 'Title of the activity', 'minLength' => 2, 'maxLength' => 200],
                    'citations' => ['type' => 'array', 'items' => ['type' => 'string', 'pattern' => '^frg_[a-z2-7]{12}$', 'description' => 'A fragment id the activity is based on'], 'minItems' => 1, 'maxItems' => 6],
                    'objectiveIds' => ['type' => 'array', 'items' => ['type' => 'string', 'description' => 'An objective id from task_input'], 'minItems' => 1, 'maxItems' => 3],
                    'data' => self::schema($library),
                ],
            ];
        }

        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'title' => 'H5P interaction',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['interaction'],
            'properties' => ['interaction' => ['anyOf' => $choices]],
        ];
    }

    /**
     * Structure and markup problems of an interaction's data.
     *
     * @return string[]
     */
    public static function errors(string $library, array $data, string $where): array
    {
        $errors = [];
        $text = fn (string $value, string $at) => Checks::markup($value, "{$where}/{$at}");
        $errors = [...$errors, ...$text((string) ($data['instruction'] ?? ''), 'instruction')];
        switch ($library) {
            case 'H5P.Blanks':
                foreach ($data['items'] ?? [] as $i => $item) {
                    $errors = [...$errors, ...$text((string) $item['text'], "items/{$i}/text")];
                    $errors = [...$errors, ...self::placeholders((string) $item['text'], count($item['blanks'] ?? []), "{$where}/items/{$i}")];
                    foreach ($item['blanks'] ?? [] as $b => $blank) {
                        foreach ([$blank['answer'] ?? '', ...($blank['alternatives'] ?? [])] as $answer) {
                            if (preg_match('/[*:\/]/', (string) $answer) || trim((string) $answer) === '') {
                                $errors[] = "{$where}/items/{$i}/blanks/{$b}: an answer cannot be empty or contain * : /";
                            }
                        }
                    }
                }
                break;
            case 'H5P.DragText':
                $errors = [...$errors, ...$text((string) ($data['text'] ?? ''), 'text'), ...self::placeholders((string) ($data['text'] ?? ''), count($data['words'] ?? []), $where)];
                foreach ([...array_column($data['words'] ?? [], 'answer'), ...($data['distractors'] ?? [])] as $w => $word) {
                    if (preg_match('/[*:\/]/', (string) $word) || trim((string) $word) === '') {
                        $errors[] = "{$where}/words/{$w}: a word cannot be empty or contain * : /";
                    }
                }
                break;
            case 'H5P.Dialogcards':
                $errors = [...$errors, ...$text((string) ($data['title'] ?? ''), 'title')];
                foreach ($data['cards'] ?? [] as $c => $card) {
                    foreach (['front', 'back', 'tip'] as $field) {
                        $errors = [...$errors, ...$text((string) ($card[$field] ?? ''), "cards/{$c}/{$field}")];
                    }
                }
                break;
            default:
                $errors[] = "{$where}: {$library} is not an allowed H5P library";
        }

        return $errors;
    }

    /**
     * Answers the cited text does not support: a blank or draggable word must appear in it, a card's
     * back must share content words with it (the quiz support check applied to H5P, ADR 0050).
     *
     * @param string[] $citedTexts
     * @return string[]
     */
    public static function unsupported(string $library, array $data, array $citedTexts, string $where, int $minOverlap = 2): array
    {
        if ($citedTexts === []) {
            return [];
        }
        $haystack = mb_strtolower(implode(' ', $citedTexts));
        $errors = [];
        $found = fn (string $answer) => $answer === '' || str_contains($haystack, mb_strtolower(trim($answer)));
        if ($library === 'H5P.Blanks') {
            foreach ($data['items'] ?? [] as $i => $item) {
                foreach ($item['blanks'] ?? [] as $b => $blank) {
                    if (!$found((string) $blank['answer'])) {
                        $errors[] = "{$where}/items/{$i}/blanks/{$b}: the answer \"{$blank['answer']}\" does not appear in the cited fragments";
                    }
                }
            }
        } elseif ($library === 'H5P.DragText') {
            foreach ($data['words'] ?? [] as $w => $word) {
                if (!$found((string) $word['answer'])) {
                    $errors[] = "{$where}/words/{$w}: the word \"{$word['answer']}\" does not appear in the cited fragments";
                }
            }
        } elseif ($library === 'H5P.Dialogcards') {
            $source = Checks::words($haystack);
            foreach ($data['cards'] ?? [] as $c => $card) {
                if (count(array_intersect_key(Checks::words($card['front'] . ' ' . $card['back']), $source)) < $minOverlap) {
                    $errors[] = "{$where}/cards/{$c}: the card shares too little with the cited fragments; cite the fragment that states it";
                }
            }
        }

        return $errors;
    }

    /** H5P content parameters (content.json) for the data. */
    public static function params(string $library, array $data): array
    {
        $html = fn (string $text) => '<p>' . htmlspecialchars(trim($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        $inline = fn (string $text) => htmlspecialchars(trim($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        switch ($library) {
            case 'H5P.Blanks':
                $questions = [];
                foreach ($data['items'] as $item) {
                    $text = $inline((string) $item['text']);
                    foreach ($item['blanks'] as $n => $blank) {
                        $marker = '*' . $inline((string) $blank['answer'])
                            . implode('', array_map(fn ($alt) => '/' . $inline((string) $alt), array_filter($blank['alternatives'] ?? [], fn ($a) => trim((string) $a) !== '')))
                            . (trim((string) ($blank['tip'] ?? '')) !== '' ? ':' . $inline((string) $blank['tip']) : '') . '*';
                        $text = str_replace('{' . ($n + 1) . '}', $marker, $text);
                    }
                    $questions[] = '<p>' . $text . '</p>';
                }

                return [
                    'text' => $html((string) $data['instruction']),
                    'questions' => $questions,
                    'behaviour' => ['enableRetry' => true, 'enableSolutionsButton' => true, 'caseSensitive' => false, 'showSolutionsRequiresInput' => true, 'autoCheck' => false, 'separateLines' => false],
                    'overallFeedback' => [['from' => 0, 'to' => 100]],
                ];
            case 'H5P.DragText':
                $text = $inline((string) $data['text']);
                foreach ($data['words'] as $n => $word) {
                    $marker = '*' . $inline((string) $word['answer']) . (trim((string) ($word['tip'] ?? '')) !== '' ? ':' . $inline((string) $word['tip']) : '') . '*';
                    $text = str_replace('{' . ($n + 1) . '}', $marker, $text);
                }

                return [
                    'taskDescription' => $html((string) $data['instruction']),
                    'textField' => $text,
                    'distractors' => implode(' ', array_map(fn ($d) => '*' . $inline((string) $d) . '*', array_filter($data['distractors'] ?? [], fn ($d) => trim((string) $d) !== ''))),
                    'behaviour' => ['enableRetry' => true, 'enableSolutionsButton' => true, 'instantFeedback' => false, 'enableCheckButton' => true],
                    'overallFeedback' => [['from' => 0, 'to' => 100]],
                ];
            case 'H5P.Dialogcards':
                return [
                    'title' => $html((string) $data['title']),
                    'mode' => 'normal',
                    'description' => $inline((string) $data['instruction']),
                    'dialogs' => array_map(fn ($card) => array_filter([
                        'text' => $inline((string) $card['front']),
                        'answer' => $inline((string) $card['back']),
                        'tips' => trim((string) ($card['tip'] ?? '')) !== '' ? ['front' => ['tip' => $html((string) $card['tip'])]] : null,
                    ], fn ($v) => $v !== null), $data['cards']),
                    'behaviour' => ['enableRetry' => true, 'disableBackwardsNavigation' => false, 'scaleTextNotCard' => false, 'randomCards' => false],
                ];
        }
        throw new \InvalidArgumentException("Unsupported H5P library {$library}");
    }

    public static function summary(string $library, array $data): string
    {
        return match ($library) {
            'H5P.Blanks' => 'Fill in the blanks · ' . array_sum(array_map(fn ($i) => count($i['blanks'] ?? []), $data['items'] ?? [])) . ' blanks',
            'H5P.DragText' => 'Drag the words · ' . count($data['words'] ?? []) . ' words',
            'H5P.Dialogcards' => 'Dialog cards · ' . count($data['cards'] ?? []) . ' cards',
            default => $library,
        };
    }

    /** @return string[] */
    private static function placeholders(string $text, int $count, string $where): array
    {
        preg_match_all('/\{(\d+)\}/', $text, $m);
        $found = array_map('intval', $m[1]);
        sort($found);

        return $found === range(1, $count) && $count > 0 ? [] : ["{$where}: the text needs the placeholders {1}…{{$count}} exactly once each, one per listed answer"];
    }
}
