<?php

namespace EscolaLms\Payments\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PaymentsExport implements FromCollection, WithHeadings
{
    private $payments;
    private array $keys = [
        'id',
        'amount',
        'currency',
        'description',
        'order_id',
        'status',
        'user_id',
        'driver',
        'created_at',
        'updated_at',
    ];

    public function __construct(Collection $payments)
    {
        $this->payments = $payments;
    }

    public function collection(): Collection
    {
        return $this->payments->map(function ($payment) {
            $result = [];
            foreach ($this->keys as $key) {
                $result[$key] = $payment[$key] ?? '';
            }

            return $result;
        });
    }

    public function headings(): array
    {
        return $this->keys;
    }
}
