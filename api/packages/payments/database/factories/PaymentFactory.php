<?php

namespace Database\Factories\EscolaLms\Payments\Models;

use EscolaLms\Payments\Facades\Payments;
use EscolaLms\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'amount' => $this->faker->numberBetween(1, 1000),
            'currency' => Payments::getPaymentsConfig()->getDefaultCurrency(),
            'description' => $this->faker->words(3, true),
            'order_id' => Str::random(10),
        ];
    }
}
