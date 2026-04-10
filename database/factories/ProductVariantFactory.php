<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
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
            'size' => $this->faker->randomElement(['PP', 'P', 'M', 'G', 'GG', 'U']),
            'color' => $this->faker->safeColorName(),
            'stock' => $this->faker->numberBetween(0, 50),
            'sku' => $this->faker->unique()->bothify('SKU-##??'),
        ];
    }
}
