<?php

namespace Tests\Integrations;

use Database\Seeders\Demo\DemoExperience;
use Database\Seeders\Demo\GravityExperience;
use Database\Seeders\Demo\PolandExperience;
use Database\Seeders\Demo\UlamExperience;
use ReflectionMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Ulams\Cart\Models\Product;

/**
 * The demo seeders take the gross prices of front/docs/design/experiences.md (€89, €490,
 * €6/month, ...) and store the net price the cart adds VAT to.
 */
class DemoPricesTest extends TestCase
{
    public static function grossPrices(): array
    {
        return [
            'Coffee Atlas course €89' => [8900, 7236],
            'On-Call seat €490' => [49000, 39837],
            'Night Sky family €6/month' => [600, 488],
            'Taste Makers €109' => [10900, 8862],
            'Taste Makers was €129' => [12900, 10488],
            'classroom €149' => [14900, 12114],
            'teams €3,900' => [390000, 317073],
            'review €150' => [15000, 12195],
        ];
    }

    #[DataProvider('grossPrices')]
    public function testNetPriceGivesExactlyTheGrossPrice(int $gross, int $net): void
    {
        $this->assertSame($net, DemoExperience::netPrice($gross, DemoExperience::TAX_RATE));

        $product = (new Product())->forceFill(['price' => $net, 'tax_rate' => DemoExperience::TAX_RATE]);
        // API gross_price (landing pages)
        $this->assertSame($gross, $product->getGrossPrice());
        // front formatPrice(price, tax_rate): net in units × (1 + rate), two decimals
        $this->assertSame(number_format($gross / 100, 2, '.', ''), number_format(round($net / 100, 2) * (1 + DemoExperience::TAX_RATE / 100), 2, '.', ''));
    }

    public static function freeExperiences(): array
    {
        return ['gravity' => [GravityExperience::class], 'poland' => [PolandExperience::class], 'ulam' => [UlamExperience::class]];
    }

    /** The three interactive academies are free and public: no product, no event, no voucher. */
    #[DataProvider('freeExperiences')]
    public function testTheInteractiveAcademiesAreFreeAndHaveNoProducts(string $class): void
    {
        $fields = (new ReflectionMethod($class, 'courseFields'))->invoke(new $class());
        $this->assertTrue($fields['public']);
        $this->assertSame([], (new ReflectionMethod($class, 'commerce'))->getParameters());
        $body = (string) file_get_contents((string) (new ReflectionMethod($class, 'commerce'))->getFileName());
        $this->assertStringNotContainsString('$this->product(', $body);
        $this->assertStringNotContainsString('$this->webinar(', $body);
        $this->assertStringNotContainsString('$this->coupon(', $body);
    }

    public function testNoTaxKeepsThePrice(): void
    {
        $this->assertSame(8900, DemoExperience::netPrice(8900, 0));
        $this->assertSame(0, DemoExperience::netPrice(0, 23));
    }
}
