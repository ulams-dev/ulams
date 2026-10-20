<?php

namespace Tests\Integrations;

use App\Support\LegacyMorphClassNames;
use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Tests\TestCase;
use Ulams\Cart\Facades\Shop;
use Ulams\Core\Models\Concerns\HasMorphTypeColumns;
use Ulams\Core\Support\MorphMap;
use Ulams\Courses\Facades\Topic;

/**
 * Class names must never be stored in polymorphic columns (see Ulams\Core\Support\MorphMap). These tests fail when a
 * new polymorphic class or owner is added without an alias.
 */
class MorphMapCoverageTest extends TestCase
{
    /** Source files that declare a morph relation but are not owners of a type column that this app stores (reason in the value). */
    private const OWNERS_WITHOUT_TYPE_COLUMNS = [
        'packages/laravel-scorm/src/Model/ScormModel.php' => 'vendored Peopleaps Scorm fork, `resource` is never written',
        'packages/notifications/src/Models/Traits/HasEventNotifications.php' => 'morphMany owner side only: the type column belongs to Laravel\'s notifications table',
        'packages/payments/src/Concerns/Payable.php' => 'morphMany owner side only: payments.payable_type is cast by Payment',
        'packages/webinar/src/Models/Webinar.php' => 'morphMany owner side only: tags.morphable_type is cast by Tag',
        'packages/courses/src/Models/Course.php' => 'morphMany owner side only: tags.morphable_type is cast by Tag',
        'packages/cart/src/Models/Product.php' => 'morphMany owner side only: tags.morphable_type is cast by Tag',
        'packages/model-fields/src/Traits/ModelFields.php' => 'morphMany owner side only: model_fields_values.class_type is cast by Field',
        'packages/model-fields/src/Models/Model.php' => 'morphMany owner side only: model_fields_values.class_type is cast by Field',
        'packages/courses/src/Models/TopicContent/AbstractTopicContent.php' => 'morphOne owner side only: topics.topicable_type is cast by Topic',
    ];

    public function test_the_map_is_enforced_and_matches_what_eloquent_uses(): void
    {
        $this->assertTrue(Relation::requiresMorphMap());
        $this->assertSame(MorphMap::all(), array_intersect_key(Relation::morphMap(), MorphMap::all()));
    }

    public function test_every_alias_names_an_existing_class_exactly_once(): void
    {
        $classes = MorphMap::all();

        foreach ($classes as $alias => $class) {
            $this->assertTrue(class_exists($class), "Morph alias [{$alias}] names a class that does not exist: {$class}");
            $this->assertSame($alias, MorphMap::aliasOf($class));
        }
        $this->assertCount(count($classes), array_unique($classes), 'A class has more than one alias');
    }

    public function test_every_topic_content_class_has_an_alias(): void
    {
        foreach (Topic::availableContentClasses() as $class) {
            if (str_contains($class, '\\Tests\\')) {
                continue;
            }
            $this->assertNotNull(MorphMap::aliasOf($class), "Topic content {$class} needs an alias: MorphMap::register(['topic.<type>' => {$class}::class]) in its service provider.");
            $this->assertStringStartsWith('topic.', MorphMap::aliasOf($class));
        }
    }

    public function test_every_productable_class_has_an_alias(): void
    {
        foreach (Shop::listRegisteredProductableClasses() as $class) {
            $this->assertNotNull(MorphMap::aliasOf($class), "Productable {$class} needs an alias: MorphMap::register(['<name>' => ...]) in its service provider.");
        }
    }

    public function test_a_model_without_an_alias_cannot_be_stored_polymorphically(): void
    {
        $this->expectException(ClassMorphViolationException::class);

        (new class extends Model {
        })->getMorphClass();
    }

    public function test_every_model_that_declares_a_morph_relation_declares_its_type_columns(): void
    {
        $root = dirname(__DIR__, 2);
        $missing = [];
        foreach ($this->phpFiles([$root . '/packages', $root . '/app']) as $file) {
            $relative = ltrim(substr($file, strlen($root)), '/');
            if (str_contains($relative, '/tests/') || isset(self::OWNERS_WITHOUT_TYPE_COLUMNS[$relative])) {
                continue;
            }
            $source = (string) file_get_contents($file);
            if (preg_match('/->morphTo\(/', $source) && !str_contains($source, 'HasMorphTypeColumns')) {
                $missing[] = $relative;
            }
        }

        $this->assertSame([], $missing, 'These models define a morphTo() relation: use HasMorphTypeColumns and list the type column in $morphTypeColumns (or add the file to OWNERS_WITHOUT_TYPE_COLUMNS with a reason).');
    }

    public function test_the_type_columns_of_every_owner_exist_in_the_schema_as_declared(): void
    {
        $owners = 0;
        $root = dirname(__DIR__, 2);
        foreach ($this->phpFiles([$root . '/packages', $root . '/app']) as $file) {
            $source = (string) file_get_contents($file);
            if (str_contains($file, '/tests/') || !str_contains($source, 'use HasMorphTypeColumns;')
                || !preg_match('/^namespace ([^;]+);/m', $source, $ns) || !preg_match('/^class (\w+)/m', $source, $name)) {
                continue;
            }
            $class = $ns[1] . '\\' . $name[1];
            $model = new $class();
            $columns = (new \ReflectionProperty($class, 'morphTypeColumns'))->getValue($model);
            $this->assertNotEmpty($columns, "{$class} uses HasMorphTypeColumns without naming a column");
            foreach ($columns as $column) {
                $this->assertTrue(\Schema::hasColumn($model->getTable(), $column), "{$class}: {$model->getTable()}.{$column} does not exist");
            }
            ++$owners;
        }

        $this->assertGreaterThan(10, $owners);
    }

    public function test_the_frozen_legacy_names_agree_with_the_registered_aliases(): void
    {
        foreach (LegacyMorphClassNames::CLASSES as $alias => $classes) {
            $registered = MorphMap::all()[$alias] ?? null;
            $this->assertNotNull($registered, "Alias [{$alias}] of the data migration is no longer registered; aliases never change.");
            $this->assertContains($registered, $classes, "Alias [{$alias}] now names a class the data migration does not know.");
        }
    }

    /** @return iterable<string> */
    private function phpFiles(array $roots): iterable
    {
        foreach ($roots as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() === 'php' && !str_contains($file->getPathname(), '/vendor/')) {
                    yield $file->getPathname();
                }
            }
        }
    }
}
