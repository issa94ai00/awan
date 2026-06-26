<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'zip' => fake()->postcode(),
            'phone' => fake()->phoneNumber(),
            'is_default' => fake()->boolean(20),
        ];
    }
}
