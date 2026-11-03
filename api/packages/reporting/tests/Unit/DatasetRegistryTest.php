<?php

namespace Ulams\Reporting\Tests\Unit;

use Illuminate\Database\Query\Builder;
use LogicException;
use PHPUnit\Framework\TestCase;
use Ulams\Reporting\Contracts\ReportDataset;
use Ulams\Reporting\Datasets\Dataset;
use Ulams\Reporting\Services\DatasetRegistry;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;

class DatasetRegistryTest extends TestCase
{
    private function dataset(string $key, string $permission = 'p', bool $available = true): ReportDataset
    {
        return new class($key, $permission, $available) extends Dataset {
            public function __construct(private string $k, private string $p, private bool $a)
            {
            }

            public function key(): string
            {
                return $this->k;
            }

            public function label(): string
            {
                return $this->k;
            }

            public function description(): string
            {
                return 'test';
            }

            public function columns(): array
            {
                return [Column::int('id', 'Id'), Column::datetime('created_at', 'Created')];
            }

            public function permission(): string
            {
                return $this->p;
            }

            public function available(): bool
            {
                return $this->a;
            }

            public function baseQuery(ReportContext $context): Builder
            {
                throw new LogicException('not run here');
            }
        };
    }

    public function testDatasetsAreFoundByKeyInRegistrationOrder(): void
    {
        $registry = new DatasetRegistry();
        $registry->register($this->dataset('b'));
        $registry->register($this->dataset('a'));

        $this->assertSame(['b', 'a'], $registry->keys());
        $this->assertTrue($registry->has('a'));
        $this->assertFalse($registry->has('missing'));
        $this->assertNull($registry->get('missing'));
        $this->assertSame(['id', 'created_at'], $registry->columnNames('a'));
    }

    public function testADatasetWhoseTablesAreMissingIsNotOffered(): void
    {
        $registry = new DatasetRegistry();
        $registry->register($this->dataset('audit', available: false));
        $registry->register($this->dataset('assignments'));

        $this->assertSame(['assignments'], $registry->keys());
        $this->assertNull($registry->get('audit'));
        $this->expectException(LogicException::class);
        $registry->columnNames('audit');
    }

    public function testRegisteringAKeyTwiceOrAnInvalidKeyFails(): void
    {
        $registry = new DatasetRegistry();
        $registry->register($this->dataset('a'));

        foreach (['a', 'Bad-Key', '1x', 'a b'] as $key) {
            try {
                $registry->register($this->dataset($key));
                $this->fail("{$key} should be refused");
            } catch (LogicException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testDateColumnsDefaultToTheTemporalOnes(): void
    {
        $this->assertSame(['created_at'], $this->dataset('a')->dateColumns());
    }
}
