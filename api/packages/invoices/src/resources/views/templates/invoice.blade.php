@php
    /** @var \Ulams\Invoices\Invoice\Invoice $invoice */
    $showDiscount = $invoice->hasItemDiscount();
    $showTax = $invoice->hasItemTax();
    $labelColspan = 3 + ($showDiscount ? 1 : 0) + ($showTax ? 1 : 0);
    $parties = ['seller' => [__('Seller'), $invoice->seller], 'buyer' => [__('Buyer'), $invoice->buyer]];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __($invoice->name) }}</title>
    <style>
        @page { margin: 36pt; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { margin: 0; color: #1f2933; font-size: 10px; line-height: 1.25; }
        p { margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; text-align: left; }
        .logo { margin-bottom: 18px; }
        .header td { padding-bottom: 18px; }
        .doc-title { font-size: 20px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .doc-status { font-size: 14px; font-weight: bold; text-transform: uppercase; color: #6b7280; margin-bottom: 6px; }
        .parties td { width: 50%; padding: 0 12px 18px 0; }
        .party-label { font-size: 14px; margin-bottom: 6px; }
        .lines th { padding: 6px 4px; border-bottom: 2px solid #d1d5db; font-weight: bold; }
        .lines td { padding: 6px 4px; border-top: 1px solid #e5e7eb; }
        .lines .summary td { border-top: none; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .muted { color: #6b7280; }
        .total { font-size: 12px; font-weight: bold; }
        .footer { margin-top: 18px; }
    </style>
</head>
<body>
@if($logo = $invoice->getLogo())
    <div class="logo"><img src="{{ $logo }}" alt="logo" height="80"></div>
@endif

<table class="header">
    <tr>
        <td><span class="doc-title">{{ __('Invoice') }}</span></td>
        <td style="width: 35%">
            @if($invoice->status)
                <div class="doc-status">{{ $invoice->status }}</div>
            @endif
            <p>{{ __('Serial No.') }} <strong>{{ $invoice->getSerialNumber() }}</strong></p>
            <p>{{ __('Invoice date') }}: <strong>{{ $invoice->getDate() }}</strong></p>
        </td>
    </tr>
</table>

<table class="parties">
    <tr>
        @foreach($parties as $role => [$label, $party])
            <td class="{{ $role }}">
                <div class="party-label">{{ $label }}</div>
                @if($party?->name)
                    <p class="{{ $role }}-name"><strong>{{ $party->name }}</strong></p>
                @endif
                @if($party?->address)
                    <p class="{{ $role }}-address">{{ __('Address') }}: {{ $party->address }}</p>
                @endif
                @if($party?->vat)
                    <p class="{{ $role }}-vat">{{ __('VAT code') }}: {{ $party->vat }}</p>
                @endif
                @if($party?->phone)
                    <p class="{{ $role }}-phone">{{ __('Phone') }}: {{ $party->phone }}</p>
                @endif
                @foreach($party?->customFields ?? [] as $key => $value)
                    <p class="{{ $role }}-custom-field">{{ __(ucfirst($key)) }}: {{ $value }}</p>
                @endforeach
            </td>
        @endforeach
    </tr>
</table>

<table class="lines">
    <thead>
    <tr>
        <th>{{ __('Description') }}</th>
        <th class="center">{{ __('Qty') }}</th>
        <th class="num">{{ __('Price') }}</th>
        @if($showDiscount)
            <th class="num">{{ __('Discount') }}</th>
        @endif
        @if($showTax)
            <th class="num">{{ __('Tax') }}</th>
        @endif
        <th class="num">{{ __('Sub total') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($invoice->items as $item)
        <tr>
            <td>
                {{ $item->title }}
                @if($item->description)
                    <p class="muted">{{ $item->description }}</p>
                @endif
            </td>
            <td class="center">{{ $item->quantity + 0 }}</td>
            <td class="num">{{ $invoice->formatCurrency($item->pricePerUnit) }}</td>
            @if($showDiscount)
                <td class="num">{{ $invoice->formatCurrency($item->discountAmount($invoice->decimals())) }}</td>
            @endif
            @if($showTax)
                <td class="num">{{ $invoice->formatCurrency($item->taxAmount($invoice->decimals())) }}</td>
            @endif
            <td class="num">{{ $invoice->formatCurrency($item->subTotal($invoice->decimals())) }}</td>
        </tr>
    @endforeach

    @if($showDiscount)
        <tr class="summary">
            <td colspan="{{ $labelColspan }}" class="num">{{ __('Total discount') }}</td>
            <td class="num">{{ $invoice->formatCurrency($invoice->totalDiscount()) }}</td>
        </tr>
    @endif
    @if($showTax)
        <tr class="summary">
            <td colspan="{{ $labelColspan }}" class="num">{{ __('Total taxes') }}</td>
            <td class="num">{{ $invoice->formatCurrency($invoice->totalTaxes()) }}</td>
        </tr>
    @endif
    <tr class="summary">
        <td colspan="{{ $labelColspan }}" class="num">{{ __('Total amount') }}</td>
        <td class="num total">{{ $invoice->formatCurrency($invoice->totalAmount()) }}</td>
    </tr>
    </tbody>
</table>

<div class="footer">
    @if($invoice->notes)
        <p>{{ __('Notes') }}: {{ $invoice->notes }}</p>
    @endif
    <p>{{ __('Amount in words') }}: {{ $invoice->getTotalAmountInWords() }}</p>
    <p>{{ __('Please pay until') }}: {{ $invoice->getPayUntilDate() }}</p>
</div>
</body>
</html>
