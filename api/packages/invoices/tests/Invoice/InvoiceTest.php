<?php

namespace Ulams\Invoices\Tests\Invoice;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Ulams\Invoices\Invoice\Invoice;
use Ulams\Invoices\Invoice\InvoiceItem;
use Ulams\Invoices\Invoice\InvoiceParty;
use Ulams\Invoices\Tests\TestCase;

class InvoiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        App::setLocale('en');
        Config::set('invoices.currency', [
            'code' => 'USD',
            'fraction' => 'ct.',
            'symbol' => '$',
            'decimals' => 2,
            'decimal_point' => ',',
            'thousands_separator' => ' ',
            'format' => '{VALUE} {SYMBOL}',
        ]);
        Config::set('invoices.serial_number', [
            'series' => 'AA',
            'sequence' => 1,
            'sequence_padding' => 5,
            'delimiter' => '.',
            'format' => '{SERIES}{DELIMITER}{SEQUENCE}',
        ]);
        Config::set('invoices.date', ['format' => 'd-m-Y', 'pay_until_days' => 7]);
        Config::set('invoices.seller.attributes', [
            'name' => 'Seller Ltd',
            'address' => 'Main St 1',
            'vat' => 'PL123',
            'custom_fields' => ['SWIFT' => 'BANK101'],
        ]);
    }

    private function invoice(): Invoice
    {
        return Invoice::make('Invoice')
            ->buyer(new InvoiceParty(name: 'Jane Buyer', address: 'Side St 2', customFields: ['order number' => 42]))
            ->sequence(42)
            ->date(Carbon::create(2026, 1, 10))
            ->addItems([
                new InvoiceItem('Course A', pricePerUnit: 100.0, quantity: 2, discount: 10.0, taxPercentage: 23.0),
                new InvoiceItem('Course B', pricePerUnit: 9.99, quantity: 3),
            ]);
    }

    public function testItemAmounts(): void
    {
        $item = new InvoiceItem('Course A', pricePerUnit: 100.0, quantity: 2, discount: 10.0, taxPercentage: 23.0);

        $this->assertSame(200.0, $item->netBeforeDiscount(2));
        $this->assertSame(190.0, $item->netAmount(2));
        $this->assertSame(233.7, $item->subTotal(2));
        $this->assertSame(43.7, $item->taxAmount(2));
        $this->assertTrue($item->hasDiscount());
        $this->assertTrue($item->hasTax());

        $plain = new InvoiceItem('Course B', pricePerUnit: 9.99, quantity: 3);
        $this->assertSame(29.97, $plain->subTotal(2));
        $this->assertSame(0.0, $plain->taxAmount(2));
        $this->assertFalse($plain->hasDiscount());
        $this->assertFalse($plain->hasTax());
    }

    public function testTotals(): void
    {
        $invoice = $this->invoice();

        $this->assertSame(263.67, $invoice->totalAmount());
        $this->assertSame(43.7, $invoice->totalTaxes());
        $this->assertSame(10.0, $invoice->totalDiscount());
        $this->assertTrue($invoice->hasItemTax());
        $this->assertTrue($invoice->hasItemDiscount());
    }

    public function testNumberingAndDates(): void
    {
        $invoice = $this->invoice();

        $this->assertSame('AA.00042', $invoice->getSerialNumber());
        $this->assertSame('10-01-2026', $invoice->getDate());
        $this->assertSame('17-01-2026', $invoice->getPayUntilDate());
    }

    public function testCurrencyFormatting(): void
    {
        $invoice = $this->invoice();

        $this->assertSame('1 234 567,89 $', $invoice->formatCurrency(1234567.891));
        $this->assertSame('0,50 $', $invoice->formatCurrency(0.5));

        Config::set('invoices.currency.format', '{CODE} {VALUE}');
        $this->assertSame('USD 12,00', $invoice->formatCurrency(12));
    }

    public function testAmountInWords(): void
    {
        $invoice = $this->invoice();

        $this->assertSame('One hundred twenty-three USD and forty-five ct.', $invoice->getAmountInWords(123.45));
        $this->assertSame('0 USD and fifty ct.', $invoice->getAmountInWords(0.5));

        Config::set('invoices.currency.decimals', 0);
        $this->assertSame('Seven USD', $invoice->getAmountInWords(7));
    }

    public function testSellerComesFromConfig(): void
    {
        $seller = $this->invoice()->seller;

        $this->assertSame('Seller Ltd', $seller->name);
        $this->assertSame('PL123', $seller->vat);
        $this->assertNull($seller->phone);
        $this->assertSame(['SWIFT' => 'BANK101'], $seller->customFields);
    }

    public function testHtmlShowsPartiesLinesAndEscapesNotes(): void
    {
        $html = $this->invoice()->notes('<b>pay fast</b>')->toHtml();

        $this->assertStringContainsString('Seller Ltd', $html);
        $this->assertStringContainsString('BANK101', $html);
        $this->assertStringContainsString('Jane Buyer', $html);
        $this->assertStringContainsString('AA.00042', $html);
        $this->assertStringContainsString('Course A', $html);
        $this->assertStringContainsString('263,67 $', $html);
        $this->assertStringContainsString('43,70 $', $html);
        $this->assertStringContainsString('&lt;b&gt;pay fast&lt;/b&gt;', $html);
    }

    public function testLogoIsEmbeddedOnlyWhenTheFileExists(): void
    {
        $this->assertNull($this->invoice()->logo('missing/logo.png')->getLogo());

        $path = tempnam(sys_get_temp_dir(), 'logo');
        // 1x1 transparent PNG
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));

        $logo = $this->invoice()->logo($path)->getLogo();
        unlink($path);

        $this->assertStringStartsWith('data:image/png;base64,', $logo);
    }

    public function testStreamRendersAPdf(): void
    {
        $response = $this->invoice()->filename('jane_fv_42')->stream();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('inline; filename="jane_fv_42.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function testRendersMultiPageInvoices(): void
    {
        $invoice = $this->invoice();
        for ($i = 0; $i < 80; $i++) {
            $invoice->addItem(new InvoiceItem('Line ' . $i, pricePerUnit: 1.0));
        }

        $pdf = $invoice->render();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
    }

    public function testSaveStoresThePdfOnTheDisk(): void
    {
        Storage::fake('public');

        $this->invoice()->filename('jane_fv_42')->save('public');

        Storage::disk('public')->assertExists('jane_fv_42.pdf');
        $this->assertStringStartsWith('%PDF-', Storage::disk('public')->get('jane_fv_42.pdf'));
    }

    public function testRequiresBuyerAndItems(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Invoice::make()->addItem(new InvoiceItem('A', 1.0))->render();
    }
}
