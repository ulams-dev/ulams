<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Support\Str;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Services\ElementLookup;
use Ulams\CourseBuilder\Tests\TestCase;

class ElementLookupTest extends TestCase
{
    public function testItResolvesAnEntityToItsElementAndSkipsRetiredEntries(): void
    {
        $session = strtolower((string) Str::ulid());
        $live = strtolower((string) Str::ulid());
        $retired = strtolower((string) Str::ulid());
        EntityMapEntry::query()->create(['session_id' => $session, 'element_id' => $live, 'entity_type' => 'topic', 'entity_id' => 11]);
        EntityMapEntry::query()->create(['session_id' => $session, 'element_id' => $retired, 'entity_type' => 'topic', 'entity_id' => 12, 'retired_at' => now()]);
        EntityMapEntry::query()->create(['session_id' => $session, 'element_id' => $live, 'entity_type' => 'gift_question', 'entity_id' => 5]);

        $lookup = new ElementLookup();

        $this->assertSame(['session_id' => $session, 'element_id' => $live], $lookup->forTopic(11));
        $this->assertSame($live, $lookup->forGiftQuestion(5)['element_id']);
        $this->assertNull($lookup->forTopic(12), 'a retired entity no longer maps');
        $this->assertNull($lookup->forTopic(999));
    }

    public function testAnUnknownElementHasNoLabel(): void
    {
        $this->assertNull((new ElementLookup())->label(strtolower((string) Str::ulid()), strtolower((string) Str::ulid())));
    }
}
