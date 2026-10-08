<?php

namespace EscolaLms\TopicTypeGift\Strategies;

use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Services\Contracts\GiftQuestionServiceContract;
use EscolaLms\TopicTypeGift\Strategies\Contracts\QuestionStrategyContract;
use Illuminate\Support\Str;

abstract class QuestionStrategy implements QuestionStrategyContract
{
    protected GiftQuestion $questionModel;
    protected GiftQuestionServiceContract $service;
    protected string $questionPlainText;

    /**
     * Base seed for randomizing the order of options presented to the student.
     * Null when the order should not be randomized (quiz has randomize_order off
     * or the question is rendered outside of an attempt). When set it is the
     * attempt id, so the randomized order is stable for a given attempt.
     */
    protected ?int $optionsSeed;

    public function __construct(GiftQuestion $questionModel, ?int $optionsSeed = null)
    {
        $this->questionModel = $questionModel;
        $this->service = app(GiftQuestionServiceContract::class);
        $this->questionPlainText = $this->service->removeComment($this->questionModel->value);
        $this->optionsSeed = $optionsSeed;
    }

    protected function shouldRandomizeOptions(): bool
    {
        return $this->optionsSeed !== null;
    }

    /**
     * Deterministic per-question seed derived from the base (attempt) seed, the
     * question id and a salt. Guarantees the randomized option order is stable
     * for a given attempt (a refresh returns the same order) while differing
     * between questions and between the parts identified by $salt (e.g. the two
     * columns of a matching question).
     */
    protected function optionsSeedFor(string $salt): int
    {
        return crc32($this->optionsSeed . '-' . $this->questionModel->getKey() . '-' . $salt);
    }

    public function getTitle(): string
    {
        if (Str::containsAll($this->questionPlainText, ['::', '::'])) {
            return Str::between($this->questionPlainText, '::', '::');
        }
        return '';
    }

    public function getQuestionForStudent(): string
    {
        $question = trim(preg_replace('/::.*?::/', '', $this->questionPlainText));
        $replacement = Str::endsWith($question, ['}']) ? '' : ' _____ ';
        $question = trim(preg_replace('/\s*\{.*?\}\s*/s', $replacement, $question));

        return $this->removeBackslashBeforeSpecialCharacters($question);
    }

    public function getOptions(): array
    {
        return [];
    }

    public function requiresManualGrading(): bool
    {
        return false;
    }

    protected function removeFeedbackFromAnswer(string $answer): string
    {
        return trim(preg_replace('/#.*$/', '', trim($answer)));
    }

    protected function removeBackslashBeforeSpecialCharacters(string $text): string
    {
        return trim(preg_replace('/\\\\([~=#\{\}:?])/', '$1', $text));
    }

    protected function escapedcharPre(string $text)
    {
        $escapedcharacters = ['\\:', '\\#', '\\=', '\\{', '\\}', '\\~', '\\n'];
        $placeholders = ['&&058;', '&&035;', '&&061;', '&&123;', '&&125;', '&&126;', '&&010'];

        $text = str_replace("\\\\", "&&092;", $text);
        $text = str_replace($escapedcharacters, $placeholders, $text);
        $text = str_replace("&&092;", "\\", $text);

        return $text;
    }

    protected function escapedcharPost(string $text)
    {
        $placeholders = ['&&058;', '&&035;', '&&061;', '&&123;', '&&125;', '&&126;', '&&010'];
        $characters   = [':', '#', '=', '{', '}', '~', '\n'];

        return str_replace($placeholders, $characters, $text);
    }
}
