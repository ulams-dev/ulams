<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ulams\CourseBuilder\Blueprint\Checks;

/**
 * The deterministic checks exist twice: here (the server) and in front/cli/src/course/checks.ts (the CLI validates a
 * course folder offline). Both run the same fixtures, so the two implementations cannot drift apart unnoticed.
 */
class ChecksFixturesTest extends TestCase
{
    /** @return iterable<string, array{0: string, 1: array<string, mixed>}> */
    public static function cases(): iterable
    {
        foreach (glob(__DIR__ . '/../fixtures/checks/*.json') ?: [] as $file) {
            $set = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            foreach ($set['cases'] as $case) {
                yield basename($file) . ': ' . $case['name'] => [$set['check'], $case];
            }
        }
    }

    /** @param array<string, mixed> $case */
    #[DataProvider('cases')]
    public function testSameIssuesAsTheCli(string $check, array $case): void
    {
        $actual = match ($check) {
            'markup' => Checks::markup($case['input'], $case['where']),
            'questionShape' => Checks::questionShape($case['input'], $case['where']),
        };

        $this->assertSame($case['expected'], $actual);
    }
}
