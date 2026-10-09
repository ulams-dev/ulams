<?php

namespace Ulams\Invoices\Invoice;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;
use NumberFormatter;

/**
 * An invoice document: parties, lines, numbering and money formatting, rendered to PDF
 * from the `invoices::templates.invoice` Blade view with dompdf.
 *
 * Defaults (dates, serial number, currency, paper, seller) come from the `invoices` config.
 */
class Invoice
{
    public string $name;
    public ?string $status = null;
    public ?string $notes = null;
    public ?string $logo = null;
    public string $filename;
    public InvoiceParty $seller;
    public ?InvoiceParty $buyer = null;
    public CarbonInterface $date;

    /** @var InvoiceItem[] */
    public array $items = [];

    private string $series;
    private string $sequence = '1';
    private ?string $output = null;

    public function __construct(?string $name = null)
    {
        $this->name = $name ?? 'Invoice';
        $this->seller = InvoiceParty::seller();
        $this->date = Carbon::now();
        $this->series = (string) config('invoices.serial_number.series', '');
        $this->sequence((int) config('invoices.serial_number.sequence', 1));
        $this->filename = $this->name;
        $this->logo = $this->resolveLogo(config('invoices.logo'));
    }

    public static function make(?string $name = null): self
    {
        return new self($name);
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function status(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function seller(InvoiceParty $seller): self
    {
        $this->seller = $seller;

        return $this;
    }

    public function buyer(InvoiceParty $buyer): self
    {
        $this->buyer = $buyer;

        return $this;
    }

    public function series(string $series): self
    {
        $this->series = $series;

        return $this;
    }

    public function sequence(int $sequence): self
    {
        $padding = (int) config('invoices.serial_number.sequence_padding', 0);
        $this->sequence = str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);

        return $this;
    }

    public function date(CarbonInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function addItem(InvoiceItem $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * @param iterable<InvoiceItem> $items
     */
    public function addItems(iterable $items): self
    {
        foreach ($items as $item) {
            $this->addItem($item);
        }

        return $this;
    }

    public function notes(string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * @param string $path absolute path, or a path relative to the public directory
     */
    public function logo(string $path): self
    {
        $this->logo = $this->resolveLogo($path);

        return $this;
    }

    /**
     * @param string $filename without the `.pdf` extension
     */
    public function filename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename . '.pdf';
    }

    /**
     * Serial number from `invoices.serial_number.format`, e.g. `AA.00042`.
     */
    public function getSerialNumber(): string
    {
        return strtr((string) config('invoices.serial_number.format', '{SERIES}{DELIMITER}{SEQUENCE}'), [
            '{SERIES}' => $this->series,
            '{DELIMITER}' => (string) config('invoices.serial_number.delimiter', ''),
            '{SEQUENCE}' => $this->sequence,
        ]);
    }

    public function getDate(): string
    {
        return $this->date->format($this->dateFormat());
    }

    public function getPayUntilDate(): string
    {
        return $this->date->copy()
            ->addDays((int) config('invoices.date.pay_until_days', 0))
            ->format($this->dateFormat());
    }

    public function decimals(): int
    {
        return (int) config('invoices.currency.decimals', 2);
    }

    public function hasItemDiscount(): bool
    {
        foreach ($this->items as $item) {
            if ($item->hasDiscount()) {
                return true;
            }
        }

        return false;
    }

    public function hasItemTax(): bool
    {
        foreach ($this->items as $item) {
            if ($item->hasTax()) {
                return true;
            }
        }

        return false;
    }

    public function totalDiscount(): float
    {
        return $this->sum(fn (InvoiceItem $item) => $item->discountAmount($this->decimals()));
    }

    public function totalTaxes(): float
    {
        return $this->sum(fn (InvoiceItem $item) => $item->taxAmount($this->decimals()));
    }

    public function totalAmount(): float
    {
        return $this->sum(fn (InvoiceItem $item) => $item->subTotal($this->decimals()));
    }

    /**
     * Amount formatted with `invoices.currency` (separators, symbol, code and format).
     */
    public function formatCurrency(float $amount): string
    {
        $value = number_format(
            $amount,
            $this->decimals(),
            (string) config('invoices.currency.decimal_point', '.'),
            (string) config('invoices.currency.thousands_separator', '')
        );

        return strtr((string) config('invoices.currency.format', '{VALUE} {SYMBOL}'), [
            '{VALUE}' => $value,
            '{SYMBOL}' => (string) config('invoices.currency.symbol', ''),
            '{CODE}' => (string) config('invoices.currency.code', ''),
        ]);
    }

    /**
     * The amount spelled out in the application locale, e.g.
     * "One hundred twenty-three USD and forty-five ct.".
     */
    public function getAmountInWords(float $amount, ?string $locale = null): string
    {
        $decimals = $this->decimals();
        $code = strtoupper((string) config('invoices.currency.code', ''));
        [$integer, $fraction] = array_pad(explode('.', number_format(abs($amount), $decimals, '.', '')), 2, '');

        $speller = new NumberFormatter(str_replace('-', '_', $locale ?? App::getLocale()), NumberFormatter::SPELLOUT);
        $integerWords = (int) $integer === 0 ? '0' : (string) $speller->format((int) $integer);

        if ($decimals <= 0) {
            return trim(ucfirst($integerWords) . ' ' . $code);
        }

        return __(':integer :code and :fraction :fraction_name', [
            'integer' => ucfirst($integerWords),
            'code' => $code,
            'fraction' => (int) $fraction === 0 ? '0' : (string) $speller->format((int) $fraction),
            'fraction_name' => (string) config('invoices.currency.fraction', ''),
        ]);
    }

    public function getTotalAmountInWords(): string
    {
        return $this->getAmountInWords($this->totalAmount());
    }

    /**
     * The logo as a data URI, for embedding in the PDF.
     */
    public function getLogo(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        $mime = mime_content_type($this->logo) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($this->logo));
    }

    public function toHtml(): string
    {
        return View::make('invoices::templates.invoice', ['invoice' => $this])->render();
    }

    /**
     * Render the PDF. The result is cached on the instance.
     */
    public function render(): string
    {
        if ($this->output !== null) {
            return $this->output;
        }

        $this->validate();

        $pdf = Pdf::setOptions(['enable_php' => false])
            ->setPaper(config('invoices.paper.size', 'a4'), config('invoices.paper.orientation', 'portrait'))
            ->loadHTML($this->toHtml());
        $pdf->render();
        $this->addPageNumbers($pdf->getDomPDF());

        return $this->output = $pdf->output();
    }

    public function stream(): Response
    {
        return new Response($this->render(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->getFilename() . '"',
        ]);
    }

    public function download(): Response
    {
        $output = $this->render();

        return new Response($output, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->getFilename() . '"',
            'Content-Length' => strlen($output),
        ]);
    }

    /**
     * Store the PDF on a filesystem disk (default: `invoices.disk`) under its filename.
     */
    public function save(?string $disk = null): self
    {
        Storage::disk($disk ?: config('invoices.disk', 'local'))->put($this->getFilename(), $this->render());

        return $this;
    }

    public function validate(): void
    {
        if (!$this->buyer) {
            throw new InvalidArgumentException('Invoice: buyer is not set.');
        }

        if (empty($this->items)) {
            throw new InvalidArgumentException('Invoice: there are no items to invoice.');
        }
    }

    private function sum(callable $amount): float
    {
        return round(array_sum(array_map($amount, $this->items)), $this->decimals());
    }

    private function dateFormat(): string
    {
        return (string) config('invoices.date.format', 'Y-m-d');
    }

    private function resolveLogo(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        foreach ([$path, public_path($path)] as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * "Page X / Y" in the bottom-right corner of multi-page invoices.
     */
    private function addPageNumbers(\Dompdf\Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();

        if ($canvas->get_page_count() < 2) {
            return;
        }

        $fontMetrics = $dompdf->getFontMetrics();
        $font = $fontMetrics->getFont('DejaVu Sans');
        $size = 9;
        $text = __('Page') . ' {PAGE_NUM} / {PAGE_COUNT}';
        $width = $fontMetrics->getTextWidth($text, $font, $size);

        $canvas->page_text($canvas->get_width() - $width - 36, $canvas->get_height() - 30, $text, $font, $size);
    }
}
