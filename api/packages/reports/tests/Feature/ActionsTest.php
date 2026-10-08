<?php

namespace Ulams\Reports\Tests\Feature;

use Ulams\Core\Tests\ApiTestTrait;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Reports\Actions\FindReport;
use Ulams\Reports\Tests\TestCase;
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
