<?php

namespace Ulams\Core\Tests\Features;

use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Ulams\Core\Models\Concerns\HasMorphAlias;
use Ulams\Core\Models\Concerns\HasMorphTypeColumns;
use Ulams\Core\Support\MorphMap;
use Ulams\Core\Tests\TestCase;

class MorphMapTest extends TestCase
{
    /** @var array<string, class-string> */
    private array $registered = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->registered = MorphMap::all();
        Schema::create('morph_probe_targets', function ($table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('morph_probe_owners', function ($table) {
            $table->id();
            $table->string('thing_type')->nullable();
            $table->unsignedBigInteger('thing_id')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('morph_probe_owners');
        Schema::dropIfExists('morph_probe_targets');
        MorphMap::flush();
        MorphMap::register($this->registered);
        MorphMap::enforce();
        parent::tearDown();
    }

    private function useProbeAliases(): void
    {
        MorphMap::register(['probe.target' => MorphProbeTarget::class]);
    }

    public function test_a_class_is_found_by_its_alias_and_an_alias_by_its_class(): void
    {
        $this->useProbeAliases();

        $this->assertSame('probe.target', MorphMap::aliasOf(MorphProbeTarget::class));
        $this->assertSame(MorphProbeTarget::class, MorphMap::classOf('probe.target'));
        $this->assertSame(MorphProbeTarget::class, MorphMap::classOf(MorphProbeTarget::class), 'a class name passes through');
        $this->assertSame('probe.target', MorphMap::canonical(MorphProbeTarget::class));
        $this->assertSame('probe.target', MorphMap::canonical('probe.target'));
        $this->assertSame('Unknown\\Thing', MorphMap::canonical('Unknown\\Thing'), 'what is not mapped is left alone');
        $this->assertNull(MorphMap::classOf(null));
    }

    public function test_a_subclass_uses_the_alias_of_its_nearest_mapped_ancestor(): void
    {
        $this->useProbeAliases();

        $this->assertSame('probe.target', MorphMap::aliasOf(MorphProbeTargetChild::class));
        $this->assertSame('probe.target', (new MorphProbeTargetChild())->getMorphClass());
    }

    public function test_the_user_alias_is_registered_by_the_core_provider(): void
    {
        $this->assertSame(\Ulams\Core\Models\User::class, MorphMap::classOf(MorphMap::USER));
        $this->assertSame(MorphMap::USER, (new \Ulams\Core\Models\User())->getMorphClass());
    }

    public function test_aliases_must_be_lower_case_words_and_cannot_be_reused(): void
    {
        $this->useProbeAliases();

        foreach (['Probe', 'probe-target', 'probe..target', '1probe', ''] as $alias) {
            try {
                MorphMap::register([$alias => MorphProbeOwner::class]);
                $this->fail("Alias [{$alias}] should be refused");
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(LogicException::class);
        MorphMap::register(['probe.target' => MorphProbeOwner::class]);
    }

    public function test_one_class_cannot_have_two_aliases(): void
    {
        $this->useProbeAliases();

        $this->expectException(LogicException::class);
        MorphMap::register(['probe.second' => MorphProbeTarget::class]);
    }

    public function test_registering_the_same_alias_for_the_same_class_again_is_allowed(): void
    {
        $this->useProbeAliases();
        $this->useProbeAliases();

        $this->assertSame(MorphProbeTarget::class, MorphMap::all()['probe.target']);
    }

    public function test_the_map_is_enforced_once_the_application_has_booted(): void
    {
        $this->assertTrue(Relation::requiresMorphMap());

        $this->expectException(ClassMorphViolationException::class);
        (new MorphProbeUnmapped())->getMorphClass();
    }

    public function test_the_database_stores_the_alias_while_the_model_and_its_queries_keep_using_class_names(): void
    {
        $this->useProbeAliases();

        $owner = MorphProbeOwner::create(['thing_type' => MorphProbeTarget::class, 'thing_id' => 7]);

        $this->assertSame('probe.target', DB::table('morph_probe_owners')->value('thing_type'));
        $this->assertSame(MorphProbeTarget::class, $owner->fresh()->thing_type);
        $this->assertSame(MorphProbeTarget::class, $owner->fresh()->toArray()['thing_type']);

        $this->assertSame(1, MorphProbeOwner::where('thing_type', MorphProbeTarget::class)->count());
        $this->assertSame(1, MorphProbeOwner::where('thing_type', 'probe.target')->count());
        $this->assertSame(1, MorphProbeOwner::where('thing_type', '=', MorphProbeTarget::class)->count());
        $this->assertSame(1, MorphProbeOwner::where(['thing_type' => MorphProbeTarget::class])->count());
        $this->assertSame(1, MorphProbeOwner::whereIn('thing_type', [MorphProbeTarget::class])->count());
        $this->assertSame(1, MorphProbeOwner::where('id', 0)->orWhere('thing_type', MorphProbeTarget::class)->count());
        $this->assertSame(0, MorphProbeOwner::where('thing_type', '!=', MorphProbeTarget::class)->count());
    }

    public function test_assigning_the_alias_or_an_unmapped_value_is_kept_as_it_is(): void
    {
        $this->useProbeAliases();

        $owner = MorphProbeOwner::create(['thing_type' => 'probe.target', 'thing_id' => 1]);
        $other = MorphProbeOwner::create(['thing_type' => 'Unknown\\Thing', 'thing_id' => 2]);

        $this->assertSame(['probe.target', 'Unknown\\Thing'], DB::table('morph_probe_owners')->orderBy('id')->pluck('thing_type')->all());
        $this->assertSame(MorphProbeTarget::class, $owner->fresh()->thing_type);
        $this->assertSame('Unknown\\Thing', $other->fresh()->thing_type);
    }

    public function test_the_relation_resolves_the_stored_alias_in_both_directions(): void
    {
        $this->useProbeAliases();
        $target = MorphProbeTarget::create(['name' => 'a']);
        $owner = MorphProbeOwner::create(['thing_type' => MorphProbeTarget::class, 'thing_id' => $target->getKey()]);

        $this->assertTrue($owner->fresh()->thing->is($target));
        $this->assertTrue(MorphProbeOwner::with('thing')->first()->thing->is($target));

        $owner->thing()->associate($target)->save();
        $this->assertSame('probe.target', DB::table('morph_probe_owners')->value('thing_type'));
    }
}

class MorphProbeTarget extends Model
{
    use HasMorphAlias;

    protected $table = 'morph_probe_targets';
    protected $guarded = [];
    public $timestamps = false;
}

class MorphProbeTargetChild extends MorphProbeTarget
{
}

class MorphProbeUnmapped extends Model
{
}

class MorphProbeOwner extends Model
{
    use HasMorphTypeColumns;

    protected $table = 'morph_probe_owners';
    protected $guarded = [];
    public $timestamps = false;
    protected $morphTypeColumns = ['thing_type'];

    public function thing()
    {
        return $this->morphTo();
    }
}
