<?php

namespace Ulams\Core\Tests\Features;

use Illuminate\Support\Str;
use Ulams\Core\Tests\TestCase;

class RequestIdTest extends TestCase
{
    public function testAValidIdIsEchoed(): void
    {
        $uuid = (string) Str::uuid();
        $ulid = (string) Str::ulid();
        $this->getJson('api/core/health-check', ['X-Request-Id' => $uuid])->assertHeader('X-Request-Id', $uuid);
        $this->getJson('api/core/health-check', ['X-Request-Id' => $ulid])->assertHeader('X-Request-Id', $ulid);
    }

    public function testAnInvalidOrMissingIdIsReplacedByAUlid(): void
    {
        foreach ([null, 'x', "evil\r\nHeader: 1", str_repeat('a', 300)] as $given) {
            $res = $this->getJson('api/core/health-check', $given === null ? [] : ['X-Request-Id' => $given]);
            $id = $res->headers->get('X-Request-Id');
            $this->assertTrue(Str::isUlid($id), (string) $id);
        }
    }
}
