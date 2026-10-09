<?php

namespace Ulams\Demo\Tests\Feature;

use Mockery;
use Mockery\MockInterface;
use Ulams\Demo\Services\H5PContentCleaner;
use Ulams\Demo\Tests\TestCase;
use Ulams\H5P\Exceptions\H5PServiceException;
use Ulams\H5P\Services\Contracts\H5PServiceClientContract;

/**
 * The H5P service client is mocked: no HTTP.
 */
class H5PContentCleanerTest extends TestCase
{
    private MockInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Mockery::mock(H5PServiceClientContract::class);
        $this->app->instance(H5PServiceClientContract::class, $this->client);
    }

    public function testDeletesEveryKnownContentThenTheOrphans(): void
    {
        $this->client->shouldReceive('delete')->once()->ordered()->with(3)->andReturnTrue();
        $this->client->shouldReceive('delete')->once()->ordered()->with(5)->andReturnFalse();
        $this->client->shouldReceive('delete')->once()->ordered()->with(8)
            ->andThrow(new H5PServiceException('H5P service error (500): boom', 500));
        $this->client->shouldReceive('deleteOrphans')->once()->ordered()
            ->andReturn(['contentIds' => ['9', '12'], 'files' => 4]);

        $result = $this->cleaner([3, 5, 8])->clean();

        $this->assertSame([
            'deleted' => 1,
            'missing' => 1,
            'failed' => [8 => 'H5P service error (500): boom'],
            'orphan_contents' => 2,
            'orphan_files' => 4,
        ], $result);
    }

    public function testStopsWhenTheServiceIsUnreachable(): void
    {
        $this->client->shouldReceive('delete')->once()->with(3)
            ->andThrow(new H5PServiceException('H5P service is unreachable: timeout'));
        $this->client->shouldNotReceive('deleteOrphans');

        $this->expectException(H5PServiceException::class);
        $this->cleaner([3, 5])->clean();
    }

    public function testStillSweepsOrphansWithoutKnownContent(): void
    {
        $this->client->shouldNotReceive('delete');
        $this->client->shouldReceive('deleteOrphans')->once()->andReturn(['contentIds' => [], 'files' => 0]);

        $this->assertSame(0, $this->cleaner([])->clean()['deleted']);
    }

    public function testIsAvailableOnlyWithAClient(): void
    {
        $this->assertTrue($this->app->make(H5PContentCleaner::class)->available());

        $this->app->offsetUnset(H5PServiceClientContract::class);
        $this->assertFalse($this->app->make(H5PContentCleaner::class)->available());
    }

    public function testKnownContentIdsAreReadFromTheTenantDatabase(): void
    {
        $ids = $this->app->make(H5PContentCleaner::class)->knownContentIds();

        $this->assertSame(array_values(array_filter($ids, fn ($id) => is_int($id) && $id > 0)), $ids);
    }

    /** @param list<int> $ids */
    private function cleaner(array $ids): H5PContentCleaner
    {
        /** @var H5PContentCleaner&MockInterface $cleaner */
        $cleaner = Mockery::mock(H5PContentCleaner::class, [$this->app])->makePartial();
        $cleaner->shouldReceive('knownContentIds')->andReturn($ids);

        return $cleaner;
    }
}
