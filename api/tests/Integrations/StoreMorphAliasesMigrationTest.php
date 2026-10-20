<?php

namespace Tests\Integrations;

use App\Support\LegacyMorphClassNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StoreMorphAliasesMigrationTest extends TestCase
{
    private const TABLE = 'morph_alias_probe';
    private const LOOKALIKE = 'morph_alias_lookalike';

    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists(self::TABLE);
        Schema::dropIfExists(self::LOOKALIKE);
        Schema::create(self::TABLE, function ($table) {
            $table->id();
            $table->string('thing_type')->nullable();
            $table->unsignedBigInteger('thing_id')->nullable();
            $table->string('note')->nullable();
        });
        // a `_type` column without an `_id` sibling is not polymorphic and must stay as it is
        Schema::create(self::LOOKALIKE, function ($table) {
            $table->id();
            $table->string('kind_type')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists(self::TABLE);
        Schema::dropIfExists(self::LOOKALIKE);
        parent::tearDown();
    }

    private function migration(): Migration
    {
        require_once base_path('database/migrations/2026_10_23_000000_store_morph_aliases.php');

        return new \StoreMorphAliases();
    }

    private function types(): array
    {
        return DB::table(self::TABLE)->orderBy('id')->pluck('thing_type')->all();
    }

    public function test_class_names_become_aliases_and_back_without_touching_anything_else(): void
    {
        DB::table(self::TABLE)->insert([
            ['thing_type' => 'Ulams\\TopicTypes\\Models\\TopicContent\\RichText', 'thing_id' => 1, 'note' => 'Ulams\\Courses\\Models\\Course'],
            ['thing_type' => 'Ulams\\Courses\\Models\\TopicContent\\H5P', 'thing_id' => 2, 'note' => null], // the location before topic types moved
            ['thing_type' => 'App\\Models\\Webinar', 'thing_id' => 3, 'note' => null],
            ['thing_type' => 'Ulams\\Courses\\Models\\Course', 'thing_id' => 4, 'note' => null],
            ['thing_type' => 'course', 'thing_id' => 5, 'note' => null], // already an alias
            ['thing_type' => 'Some\\Plugin\\Thing', 'thing_id' => 6, 'note' => null], // not known: left alone
            ['thing_type' => null, 'thing_id' => null, 'note' => null],
        ]);
        DB::table(self::LOOKALIKE)->insert(['kind_type' => 'Ulams\\Courses\\Models\\Course']);

        $this->migration()->up();

        $this->assertSame(['topic.rich_text', 'topic.h5p', 'webinar', 'course', 'course', 'Some\\Plugin\\Thing', null], $this->types());
        $this->assertSame('Ulams\\Courses\\Models\\Course', DB::table(self::TABLE)->orderBy('id')->value('note'), 'other columns are untouched');
        $this->assertSame('Ulams\\Courses\\Models\\Course', DB::table(self::LOOKALIKE)->value('kind_type'), 'a column without an _id sibling is untouched');

        $this->migration()->up();
        $this->assertSame(['topic.rich_text', 'topic.h5p', 'webinar', 'course', 'course', 'Some\\Plugin\\Thing', null], $this->types(), 'running it again changes nothing');

        $this->migration()->down();
        $this->assertSame([
            'Ulams\\TopicTypes\\Models\\TopicContent\\RichText',
            'Ulams\\TopicTypes\\Models\\TopicContent\\H5P',
            'Ulams\\Webinar\\Models\\Webinar',
            'Ulams\\Courses\\Models\\Course',
            'Ulams\\Courses\\Models\\Course',
            'Some\\Plugin\\Thing',
            null,
        ], $this->types());
    }

    public function test_the_real_polymorphic_columns_are_found(): void
    {
        $columns = array_map(fn (array $c) => $c[0] . '.' . $c[1], LegacyMorphClassNames::typeColumns(DB::connection()->getDriverName()));

        foreach (['topics.topicable_type', 'tags.morphable_type', 'order_items.buyable_type', 'products_productables.productable_type', 'payments.payable_type', 'model_has_roles.model_type', 'notifications.notifiable_type'] as $expected) {
            $this->assertContains($expected, $columns);
        }
        $this->assertNotContains('notifications.type', $columns);
    }
}
