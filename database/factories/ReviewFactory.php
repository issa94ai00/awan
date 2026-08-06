<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional()->paragraph(),
            'images' => fake()->optional()->randomElements([fake()->imageUrl(), fake()->imageUrl()], rand(0, 3)),
        ];
    }
}
