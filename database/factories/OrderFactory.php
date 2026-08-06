<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 20, 500);
        $shippingCost = 10;
        $tax = $subtotal * 0.1;
        
        return [
            'status' => fake()->randomElement(['pending', 'processing', 'shipped', 'delivered', 'canceled', 'returned']),
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'tax' => $tax,
            'total' => $subtotal + $shippingCost + $tax,
            'payment_method_type' => fake()->randomElement(['card', 'cash', 'wallet']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
