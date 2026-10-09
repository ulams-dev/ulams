<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Ulams\Commerce\CommerceManager;
use Ulams\Commerce\Contracts\CommerceProvider;
use Ulams\Commerce\Support\CheckoutRef;
use Ulams\Commerce\Support\OrderEvent;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\SellableRef;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Core\Models\User;

/** Pricing in the builder: the suggested price, the inactive product at apply and its activation at publish. */
class CommerceStepTest extends TestCase
{
    private object $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new class () implements CommerceProvider {
            public array $calls = [];
            public bool $rejectCurrency = false;

            public function key(): string
            {
                return 'fake';
            }

            public function syncProduct(SellableRef $ref, Price $price, bool $active): ProductRef
            {
                if ($this->rejectCurrency) {
                    throw new InvalidArgumentException('The cart sells in USD.');
                }
                $this->calls[] = [$ref->type, $ref->id, $price->amountMinor, $price->currency, $active];

                return new ProductRef('fake', "course-{$ref->id}");
            }

            public function createCheckout(User $user, ProductRef $product, string $returnUrl): CheckoutRef
            {
                return new CheckoutRef('https://pay.test');
            }

            public function handleOrderEvent(Request $request): ?OrderEvent
            {
                return null;
            }
        };
        app(CommerceManager::class)->extend('fake', fn () => $this->fake);
        config(['commerce.provider' => 'fake']);
    }

    private function setPrice($author, Session $session, array $pricing): void
    {
        $this->actingAs($author, 'api')->putJson("/api/admin/course-builder/sessions/{$session->id}/brief", ['brief' => ['pricing' => $pricing]])->assertOk();
    }

    private function applied(array $pricing): array
    {
        $author = $this->author();
        $session = $this->toApplyReview($author);
        $this->setPrice($author, $session, $pricing);
        $version = $session->refresh()->current_version_id;
        $this->action($author, $session, 'approve_apply', "apply-{$version}", ['versionId' => $version])->assertStatus(202);

        return [$author, $session->refresh()];
    }

    public function testAPaidCourseGetsAnInactiveProductAtApplyAndItsActivatedAtPublish(): void
    {
        [$author, $session] = $this->applied(['mode' => 'paid', 'amountMinor' => 4900, 'currency' => 'USD']);

        $this->assertSame([['course', (int) $session->course_id, 4900, 'USD', false]], $this->fake->calls);
        $this->assertSame([], $session->stateValue('applyNotes'));

        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/publish")->assertOk();

        $this->assertSame(['course', (int) $session->course_id, 4900, 'USD', true], $this->fake->calls[1]);
    }

    public function testAFreeCourseNeverGetsAProduct(): void
    {
        [$author, $session] = $this->applied(['mode' => 'free']);
        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/publish")->assertOk();

        $this->assertSame([], $this->fake->calls);
    }

    public function testAPaidCourseWithoutAnAmountIsAppliedWithANote(): void
    {
        [, $session] = $this->applied(['mode' => 'paid']);

        $this->assertSame([], $this->fake->calls);
        $this->assertStringContainsString('no price yet', $session->stateValue('applyNotes.0'));
        $this->assertSame(Session::APPLIED, $session->status);
    }

    public function testACurrencyTheProviderCannotSellDoesNotBlockTheApply(): void
    {
        $this->fake->rejectCurrency = true;
        [, $session] = $this->applied(['mode' => 'paid', 'amountMinor' => 4900, 'currency' => 'EUR']);

        $this->assertStringContainsString('The product was not created', $session->stateValue('applyNotes.0'));
        $this->assertSame(Session::APPLIED, $session->status);
    }

    public function testAPriceIsSuggestedOnlyForAPaidCourseWithoutAnAmount(): void
    {
        $author = $this->author();
        $session = $this->uploaded($author);
        $this->action($author, $session, 'answer', 'interview', ['key' => 'pricing', 'value' => ['mode' => 'paid']])->assertStatus(202);
        $this->action($author, $session, 'answer', 'interview', ['key' => 'duration', 'value' => ['totalMinutes' => 60, 'lessonMinutes' => 10]])->assertStatus(202);
        $this->action($author, $session, 'decide_for_me', 'interview')->assertStatus(202);
        $session->refresh();
        $outline = $session->current_version_id;
        $this->action($author, $session, 'approve_outline', "outline-{$outline}", ['versionId' => $outline])->assertStatus(202);

        $suggestion = $session->refresh()->stateValue('priceSuggestion');
        $this->assertGreaterThanOrEqual(500, $suggestion['amountMinor']);
        $this->assertTrue($suggestion['suggested']);
        $this->assertSame('USD', $suggestion['currency']);
        $this->assertSame(['mode' => 'paid'], $session->brief['pricing'], 'a suggestion never fills the brief');
        $this->actingAs($author, 'api')->getJson("/api/admin/course-builder/sessions/{$session->id}")->assertJsonPath('data.priceSuggestion.suggested', true);
        $this->assertDatabaseHas('ai_calls', ['task' => 'price', 'subject_id' => $session->id]);
    }

    public function testNoSuggestionForAFreeCourse(): void
    {
        $session = $this->toApplyReview($this->author());

        $this->assertNull($session->stateValue('priceSuggestion'));
        $this->assertDatabaseMissing('ai_calls', ['task' => 'price', 'subject_id' => $session->id]);
    }
}
