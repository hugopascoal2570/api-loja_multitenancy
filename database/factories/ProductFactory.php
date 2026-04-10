<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'name' => $this->faker->words(2, true),
            'reference' => $this->faker->optional()->bothify('REF-###??'),
            'description' => $this->faker->paragraph(),
            'retail_price' => $this->faker->randomFloat(2, 30, 80),
            'wholesale_price' => $this->faker->randomFloat(2, 20, 29),
            'wholesale_min_qty' => $this->faker->numberBetween(10, 50),
        ];
    }
}
