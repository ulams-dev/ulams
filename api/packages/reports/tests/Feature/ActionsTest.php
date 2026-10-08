<?php

namespace EscolaLms\Reports\Tests\Feature;

use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Reports\Actions\FindReport;
use EscolaLms\Reports\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use InvalidArgumentException;

class ActionsTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;

    public function testFindReportActionThrowsException(): void
    {
        $action = app(FindReport::class);

        $this->expectException(InvalidArgumentException::class);

        $action->handle(self::class);
    }
}
