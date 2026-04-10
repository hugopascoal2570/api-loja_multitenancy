<?php

// database/seeders/CategorySeeder.php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Camisas', 'description' => 'Roupas da parte superior como blusas e camisetas.'],
            ['name' => 'Calças', 'description' => 'Calças jeans, leggings e outras.'],
            ['name' => 'Shorts', 'description' => 'Peças curtas para o verão.'],
            ['name' => 'Bodies', 'description' => 'Bodies femininos de diversas cores e modelos.'],
        ];

        foreach ($categories as $cat) {
            Category::create([
                'id' => Str::uuid(),
                'name' => $cat['name'],
                'slug' => Str::slug($cat['name']),
                'description' => $cat['description'],
            ]);
        }
    }
}
