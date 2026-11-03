<?php

namespace Ulams\Commerce\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Ulams\Commerce\Jobs\SyncSellableJob;
use Ulams\Commerce\Models\AccessPass;
use Ulams\Commerce\Models\Bundle;
use Ulams\Commerce\Models\BundleCourse;
use Ulams\Commerce\Models\DriftReport;
use Ulams\Commerce\Models\ProductLink;
use Ulams\Commerce\Models\SellablePrice;
use Ulams\Commerce\Services\CatalogueSync;
use Ulams\Commerce\Support\DriftItem;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\ProductState;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Commerce\Tests\Support\OrderFlowTestCase;
use Ulams\CourseAccess\Models\Course;

/**
 * Catalogue sync (ADR 0130): the LMS holds the price and the intent, the provider holds a product that is enabled only
 * while the sellable is for sale and published. Runs against the in-memory provider, so it needs no Sylius.
 */
class CatalogueSyncTest extends OrderFlowTestCase
{
    private function sync(): CatalogueSync
    {
        return app(CatalogueSync::class);
    }

    private function published(string $title = 'Brewing basics', string $status = 'published'): Course
    {
        return Course::factory()->create(['title' => $title, 'status' => $status]);
    }

    private function bundleOf(Course ...$courses): Bundle
    {
        $bundle = Bundle::query()->create(['name' => 'Coffee set', 'description' => 'Two courses']);
        foreach ($courses as $course) {
            BundleCourse::query()->create(['bundle_id' => $bundle->getKey(), 'course_id' => $course->getKey()]);
        }

        return $bundle;
    }

    public function testThePriceIsTheLmsIntentAndTheProductFollowsIt(): void
    {
        $course = $this->published();
        $ref = SellableRef::course($course->getKey());

        $this->sync()->setPrice($ref, new Price(4900, 'EUR'));
        $this->assertSame([], $this->sylius->syncCalls, 'recording a price does not call the provider');
        $this->sync()->push($ref);

        $state = $this->sylius->products["variant-course-{$course->getKey()}"];
        $this->assertSame(['Brewing basics', 4900, 'EUR', true], [$state->name, $state->priceMinor, $state->currency, $state->enabled]);
        $this->assertDatabaseHas('commerce_sellable_prices', ['sellable_type' => 'course', 'sellable_id' => $course->getKey(), 'amount_minor' => 4900, 'for_sale' => true]);
    }

    public function testADraftCourseIsNotOnSaleAndNeitherIsOneTheTenantTookOffSale(): void
    {
        $draft = $this->published('Draft', 'draft');
        $live = $this->published();
        $this->sync()->setPrice(SellableRef::course($draft->getKey()), new Price(1000, 'EUR'));
        $this->sync()->setPrice(SellableRef::course($live->getKey()), new Price(1000, 'EUR'), forSale: false);

        $this->sync()->push(SellableRef::course($draft->getKey()));
        $this->sync()->push(SellableRef::course($live->getKey()));

        $this->assertFalse($this->sylius->products["variant-course-{$draft->getKey()}"]->enabled);
        $this->assertFalse($this->sylius->products["variant-course-{$live->getKey()}"]->enabled);
    }

    public function testASellableWithoutAPriceIsNeverSent(): void
    {
        $this->assertNull($this->sync()->push(SellableRef::course($this->published()->getKey())));
        $this->assertSame([], $this->sylius->syncCalls);
    }

    public function testAnUnchangedProductIsNotSentAgainUnlessForced(): void
    {
        $ref = SellableRef::course($this->published()->getKey());
        $this->sync()->setPrice($ref, new Price(4900, 'EUR'));

        $this->assertNotNull($this->sync()->push($ref));
        $this->assertNull($this->sync()->push($ref));
        $this->assertCount(1, $this->sylius->syncCalls);

        $this->assertNotNull($this->sync()->push($ref, force: true));
        $this->sync()->setPrice($ref, new Price(5900, 'EUR'));
        $this->assertNotNull($this->sync()->push($ref));
        $this->assertCount(3, $this->sylius->syncCalls);
    }

    public function testAPriceForASellableThatDoesNotExistIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->sync()->setPrice(SellableRef::course(987654), new Price(100, 'EUR'));
    }

    public function testUnpublishingAndPublishingACourseTogglesItsProductThroughTheQueue(): void
    {
        $course = $this->published();
        $ref = SellableRef::course($course->getKey());
        $this->sync()->setPrice($ref, new Price(4900, 'EUR'));
        $this->sync()->push($ref);
        $key = "variant-course-{$course->getKey()}";
        $this->assertTrue($this->sylius->products[$key]->enabled);

        $course->update(['status' => 'draft']);
        $this->assertFalse($this->sylius->products[$key]->enabled, 'unpublished: disabled, never deleted');

        $course->update(['status' => 'published']);
        $this->assertTrue($this->sylius->products[$key]->enabled);
    }

    public function testACourseThatIsNotForSaleCostsNothingToPublish(): void
    {
        $course = $this->published('Free one', 'draft');
        $course->update(['status' => 'published']);

        $this->assertSame([], $this->sylius->syncCalls);
    }

    public function testBundlesAndPassesFollowTheirCoursesStatus(): void
    {
        [$a, $b] = [$this->published('A'), $this->published('B')];
        $bundle = $this->bundleOf($a, $b);
        $pass = AccessPass::query()->create(['name' => 'A for 30 days', 'subject_type' => 'course', 'subject_id' => $a->getKey(), 'days' => 30]);
        $bundlePass = AccessPass::query()->create(['name' => 'Set for 90 days', 'subject_type' => 'bundle', 'subject_id' => $bundle->getKey(), 'days' => 90]);
        foreach ([SellableRef::bundle($bundle->getKey()), SellableRef::accessPass($pass->getKey()), SellableRef::accessPass($bundlePass->getKey())] as $ref) {
            $this->sync()->setPrice($ref, new Price(2900, 'EUR'));
            $this->sync()->push($ref);
        }
        $enabled = fn (string $key) => $this->sylius->products[$key]->enabled;
        $this->assertTrue($enabled("variant-bundle-{$bundle->getKey()}"));

        $b->update(['status' => 'draft']);
        $this->assertFalse($enabled("variant-bundle-{$bundle->getKey()}"), 'a bundle is on sale only while every course in it is published');
        $this->assertFalse($enabled("variant-access_pass-{$bundlePass->getKey()}"));
        $this->assertTrue($enabled("variant-access_pass-{$pass->getKey()}"), 'a pass on the other course is unaffected');

        $a->update(['status' => 'draft']);
        $this->assertFalse($enabled("variant-access_pass-{$pass->getKey()}"));
        $a->update(['status' => 'published']);
        $b->update(['status' => 'published']);
        $this->assertTrue($enabled("variant-bundle-{$bundle->getKey()}"));
        $this->assertTrue($enabled("variant-access_pass-{$bundlePass->getKey()}"));
    }

    public function testAnAccessPassCarriesItsDaysAndTheNameOfThePass(): void
    {
        $course = $this->published();
        $pass = AccessPass::query()->create(['name' => 'Brewing, 30 days', 'subject_type' => 'course', 'subject_id' => $course->getKey(), 'days' => 30]);

        $this->sync()->setPrice(SellableRef::accessPass($pass->getKey()), new Price(1900, 'EUR'));
        $spec = $this->sync()->spec(SellableRef::accessPass($pass->getKey()));

        $this->assertSame(['Brewing, 30 days', 30, true], [$spec->name, $spec->accessDays, $spec->active]);
    }

    public function testAFailedPushKeepsTheReasonOnTheLinkAndIsRetriedEvenIfTheSpecIsUnchanged(): void
    {
        $ref = SellableRef::course($this->published()->getKey());
        $this->sync()->setPrice($ref, new Price(4900, 'EUR'));
        $this->sync()->push($ref);
        $this->sync()->setPrice($ref, new Price(5900, 'EUR'));
        $this->sylius->syncFails = 'Sylius is down';

        try {
            $this->sync()->push($ref);
            $this->fail('expected the failure to be rethrown for the job to retry');
        } catch (\RuntimeException $e) {
            $this->assertSame('Sylius is down', $e->getMessage());
        }
        $this->assertSame('Sylius is down', ProductLink::query()->firstOrFail()->sync_error);

        $this->sylius->syncFails = null;
        $this->sync()->push($ref);
        $link = ProductLink::query()->firstOrFail();
        $this->assertNull($link->sync_error);
        $this->assertSame(5900, $link->amount_minor);
    }

    public function testTheCheckFindsDriftAndRepairPutsItRightAndStoresTheReport(): void
    {
        $course = $this->published();
        $ref = SellableRef::course($course->getKey());
        $this->sync()->setPrice($ref, new Price(4900, 'EUR'));
        $this->sync()->push($ref);
        $this->assertSame([], $this->sync()->check());

        $key = "variant-course-{$course->getKey()}";
        $this->sylius->products[$key] = new ProductState($this->sylius->products[$key]->ref, 'Renamed in the Sylius admin', 100, 'EUR', false);

        $kinds = array_map(fn (DriftItem $d) => $d->kind, $this->sync()->check());
        $this->assertEqualsCanonicalizing(['price', 'enabled', 'name'], $kinds);

        $result = $this->sync()->checkAndRepair(true);
        $this->assertSame(3, $result['repaired']);
        $this->assertSame([], $this->sync()->check());
        $report = DriftReport::query()->where('kind', 'catalogue')->latest('id')->firstOrFail();
        $this->assertSame([3, 3], [$report->drift, $report->repaired]);
        $this->assertSame(4900, $this->sylius->products[$key]->priceMinor);
    }

    public function testAProductDeletedAtTheProviderIsReportedMissingAndRecreated(): void
    {
        $course = $this->published();
        $ref = SellableRef::course($course->getKey());
        $this->sync()->setPrice($ref, new Price(4900, 'EUR'));
        $this->sync()->push($ref);
        unset($this->sylius->products["variant-course-{$course->getKey()}"]);

        $this->assertSame([DriftItem::MISSING], array_map(fn (DriftItem $d) => $d->kind, $this->sync()->check()));
        $this->sync()->checkAndRepair(true);
        $this->assertSame([], $this->sync()->check());
    }

    public function testASellableNeverPushedIsMissingAndAFailedOneIsAnError(): void
    {
        $a = SellableRef::course($this->published('A')->getKey());
        $b = SellableRef::course($this->published('B')->getKey());
        $this->sync()->setPrice($a, new Price(100, 'EUR'));
        $this->sync()->setPrice($b, new Price(100, 'EUR'));
        $this->sync()->push($b);
        $this->sylius->syncFails = 'boom';
        try {
            $this->sync()->push($b, force: true);
        } catch (\RuntimeException) {
        }

        $byKind = collect($this->sync()->check())->mapWithKeys(fn (DriftItem $d) => [$d->sellable->key() => $d->kind]);
        $this->assertSame([$a->key() => 'missing', $b->key() => 'error'], $byKind->all());
    }

    public function testASellableThatNoLongerExistsIsLeftAlone(): void
    {
        $course = $this->published();
        $this->sync()->setPrice(SellableRef::course($course->getKey()), new Price(100, 'EUR'));
        $course->delete();

        $this->assertSame([], $this->sync()->check());
        $this->assertNull($this->sync()->push(SellableRef::course($course->getKey())));
    }

    public function testTheCommandPushesCheckAndRepairsAndReportsThroughItsExitCode(): void
    {
        $a = SellableRef::course($this->published('A')->getKey());
        $b = SellableRef::bundle($this->bundleOf($this->published('B'))->getKey());
        $this->sync()->setPrice($a, new Price(100, 'EUR'));
        $this->sync()->setPrice($b, new Price(200, 'EUR'));

        $this->assertSame(1, Artisan::call('ulams:commerce:sync', ['--check' => true]), 'drift: nothing was ever pushed');
        $this->assertSame(0, Artisan::call('ulams:commerce:sync', ['--sellable' => $a->key()]));
        $this->assertCount(1, $this->sylius->syncCalls);
        $this->assertSame(0, Artisan::call('ulams:commerce:sync'));
        $this->assertCount(2, $this->sylius->syncCalls, 'only the one that was not yet in step');
        $this->assertSame(0, Artisan::call('ulams:commerce:sync', ['--check' => true]));
        $this->sylius->products["variant-bundle-{$b->id}"] = new ProductState($this->sylius->products["variant-bundle-{$b->id}"]->ref, 'X', 1, 'EUR', true);
        $this->assertSame(1, Artisan::call('ulams:commerce:sync', ['--check' => true]));
        $this->assertSame(0, Artisan::call('ulams:commerce:sync', ['--check' => true, '--repair' => true]));
        $this->assertSame(2, Artisan::call('ulams:commerce:sync', ['--sellable' => 'cow:1']));
        $this->assertSame(1, Artisan::call('ulams:commerce:sync', ['--provider' => 'nope']));
    }

    public function testAProviderThatCannotSellABundleIsNotRetried(): void
    {
        $this->sylius->syncFails = null;
        app(\Ulams\Commerce\CommerceManager::class)->extend('limited', fn () => new class () extends \Ulams\Commerce\Tests\Support\FakeCommerceProvider {
            public function key(): string
            {
                return 'limited';
            }

            public function syncProduct(SellableRef $ref, \Ulams\Commerce\Support\ProductSpec $spec): \Ulams\Commerce\Support\ProductRef
            {
                throw new \Ulams\Commerce\Support\UnsupportedSellable('courses only');
            }
        });
        $bundle = $this->bundleOf($this->published());
        SellablePrice::query()->create(['sellable_type' => 'bundle', 'sellable_id' => $bundle->getKey(), 'amount_minor' => 100, 'currency' => 'EUR', 'for_sale' => true]);

        (new SyncSellableJob('bundle', $bundle->getKey(), 'limited'))->handle($this->sync());

        $this->assertTrue(true, 'the job finished without throwing, so the queue does not retry it');
    }
}
