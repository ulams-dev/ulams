<?php

namespace EscolaLms\TopicTypeGift\Tests\Service;

use EscolaLms\TopicTypeGift\Exceptions\UnknownGiftTypeException;
use EscolaLms\TopicTypeGift\Services\Contracts\GiftQuestionServiceContract;
use EscolaLms\TopicTypeGift\Tests\GiftQuestionTesting;
use EscolaLms\TopicTypeGift\Tests\TestCase;

class GiftQuestionServiceTest extends TestCase
{
    use GiftQuestionTesting;

    private GiftQuestionServiceContract $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(GiftQuestionServiceContract::class);
    }

    /**
     * @dataProvider questionDataProvider
     * @throws UnknownGiftTypeException
     */
    public function testReturnCorrectQuestionType(string $question, string $type): void
    {
        $this->assertEquals($this->service->getType($question), $type);
    }
}
