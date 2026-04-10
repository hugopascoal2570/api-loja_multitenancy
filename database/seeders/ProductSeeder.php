<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use App\Models\ProductKit;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();

        Product::factory()
            ->count(10)
            ->make()
            ->each(function ($product) use ($categories) {
                $category = $categories->random();

                $retailPrice = fake()->randomFloat(2, 50, 150);
                $isPromotion = fake()->boolean(50);
                $isHighlighted = fake()->boolean(30);

                $promotionPrice = null;
                $promotionPercent = null;

                if ($isPromotion) {
                    $promotionPrice = round($retailPrice * fake()->randomFloat(2, 0.75, 0.95), 2);
                    $promotionPercent = round(100 - ($promotionPrice / $retailPrice * 100), 2);
                }

                $product->fill([
                    'id' => Str::uuid(),
                    'category_id' => $category->id,
                    'retail_price' => $retailPrice,
                    'wholesale_price' => $retailPrice * 0.7,
                    'wholesale_min_qty' => rand(10, 30),
                    'is_highlighted' => $isHighlighted,
                    'is_promotion' => $isPromotion,
                    'promotion_price' => $promotionPrice,
                    'promotion_percent' => $promotionPercent,
                    'is_new' => fake()->boolean(40),
                    'is_new_collection' => fake()->boolean(30),
                    'active' => true,
                    'name' => $name = fake()->words(3, true),
                    'slug' => Str::slug($name),
                    'reference' => strtoupper(fake()->bothify('??-###')),
                    'description' => fake()->sentence(10),
                ])->save();

                $sizes = ['PP', 'P', 'M', 'G', 'GG', 'U'];
                $colors = ['vermelho', 'azul', 'preto'];

                foreach ($sizes as $size) {
                    foreach ($colors as $color) {
                        ProductVariant::factory()->create([
                            'product_id' => $product->id,
                            'size' => $size,
                            'color' => $color,
                        ]);
                    }
                }

                foreach (array_slice($colors, 0, rand(1, 3)) as $i => $color) {
                    ProductImage::factory()->create([
                        'product_id' => $product->id,
                        'color' => $color,
                        'is_main' => $i === 0,
                    ]);
                }

                // Criar kits aleatoriamente
                if (fake()->boolean(50)) {
                    $kitConfigs = [
                        [
                            'name' => 'Box Tamanho M',
                            'description' => '10 peças tamanho M em várias cores.',
                            'total_quantity' => 10,
                            'fixed_size' => 'M',
                            'fixed_color' => null,
                        ],
                        [
                            'name' => 'Box Azul',
                            'description' => 'Kit com peças da cor azul, tamanhos variados.',
                            'total_quantity' => 10,
                            'fixed_size' => null,
                            'fixed_color' => 'azul',
                        ],
                        [
                            'name' => 'Box Misto',
                            'description' => 'Kit misto com tamanhos e cores diferentes.',
                            'total_quantity' => 8,
                            'fixed_size' => null,
                            'fixed_color' => null,
                        ]
                    ];

                    foreach ($kitConfigs as $config) {
                        if (fake()->boolean(50)) {
                            $kit = $product->kits()->create([
                                'name' => $config['name'],
                                'description' => $config['description'],
                                'total_quantity' => $config['total_quantity'],
                                'price' => $retailPrice * $config['total_quantity'] * 0.9,
                                'fixed_size' => $config['fixed_size'],
                                'fixed_color' => $config['fixed_color'],
                            ]);

                            // Apenas para kits mistos: gerar itens variados
                            if (is_null($config['fixed_size']) && is_null($config['fixed_color'])) {
                                $variants = $product->variants()->inRandomOrder()->take(3)->get();

                                foreach ($variants as $variant) {
                                    $kit->items()->create([
                                        'variant_id' => $variant->id,
                                        'quantity' => rand(2, 4),
                                    ]);
                                }
                            }
                        }
                    }
                }
            });
    }
}
