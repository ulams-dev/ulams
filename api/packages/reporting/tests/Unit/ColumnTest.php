<?php

namespace Ulams\Reporting\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ulams\Reporting\Support\Column;

class ColumnTest extends TestCase
{
    public function testCapabilitiesFollowTheType(): void
    {
        $this->assertSame(['eq', 'neq', 'in', 'not_in', 'contains', 'is_null'], Column::string('title', 'Title')->filterOps);
        $this->assertContains('between', Column::int('n', 'N')->filterOps);
        $this->assertNotContains('contains', Column::int('n', 'N')->filterOps);
        $this->assertNotContains('in', Column::decimal('x', 'X')->filterOps);
        $this->assertSame(['eq', 'is_null'], Column::bool('flag', 'Flag')->filterOps);
        $this->assertContains('between', Column::datetime('at', 'At')->filterOps);

        $this->assertTrue(Column::int('n', 'N')->aggregatable);
        $this->assertTrue(Column::decimal('x', 'X')->aggregatable);
        $this->assertFalse(Column::string('s', 'S')->aggregatable);
        $this->assertFalse(Column::datetime('at', 'At')->aggregatable);
        $this->assertTrue(Column::datetime('at', 'At')->groupable);
        $this->assertFalse(Column::decimal('x', 'X')->groupable, 'a measure is aggregated, not grouped on');
    }

    public function testAPersonalColumnIsNeverGroupableOrAggregatable(): void
    {
        $email = Column::string('email', 'E-mail', personal: true);

        $this->assertTrue($email->personal);
        $this->assertFalse($email->groupable);
        $this->assertFalse($email->aggregatable);
    }

    public function testEnumColumnsListTheirValuesAndSerialise(): void
    {
        $column = Column::enum('status', 'Status', ['open', 'done']);

        $this->assertSame(['open', 'done'], $column->enumValues);
        $this->assertSame(['name' => 'status', 'type' => 'enum', 'label' => 'Status', 'filterOps' => ['eq', 'neq', 'in', 'not_in', 'is_null'], 'groupable' => true, 'aggregatable' => false, 'personal' => false, 'enumValues' => ['open', 'done']], $column->toArray());
    }

    public function testInvalidDeclarationsAreRejected(): void
    {
        foreach ([
            fn () => Column::string('Bad Name', 'x'),
            fn () => Column::string('1st', 'x'),
            fn () => Column::string('a;drop table users', 'x'),
            fn () => Column::enum('status', 'Status', []),
        ] as $declare) {
            try {
                $declare();
                $this->fail('expected the declaration to be rejected');
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }
}
