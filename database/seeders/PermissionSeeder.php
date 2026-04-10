<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $existing = DB::table('permissions')->pluck('name')->toArray();

        $routes = collect(Route::getRoutes())
            ->filter(fn ($route) => $route->getName())
            ->map(fn ($route) => [
                'id' => Str::uuid(),
                'name' => $route->getName(),
                'description' => $route->uri(),
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->reject(fn ($perm) => in_array($perm['name'], $existing))
            ->unique('name');

        if ($routes->isNotEmpty()) {
            DB::table('permissions')->insert($routes->toArray());
        }
    }
}
