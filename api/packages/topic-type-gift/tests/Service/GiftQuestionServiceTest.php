<?php

namespace Ulams\TopicTypeGift\Tests\Service;

use Ulams\TopicTypeGift\Exceptions\UnknownGiftTypeException;
use Ulams\TopicTypeGift\Services\Contracts\GiftQuestionServiceContract;
use Ulams\TopicTypeGift\Tests\GiftQuestionTesting;
use Ulams\TopicTypeGift\Tests\TestCase;

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
