<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'card_number_last4' => fake()->randomNumber(4, true),
            'cardholder_name' => fake()->name(),
            'expiry_date' => fake()->creditCardExpirationDateString(),
            'card_type' => fake()->randomElement(['visa', 'mastercard', 'amex']),
            'is_default' => fake()->boolean(20),
        ];
    }
}
