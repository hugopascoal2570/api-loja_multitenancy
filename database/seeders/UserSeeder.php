<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->count(50)->create();

        User::create([
            'name' => 'Hugo Pascoal', 
            'email' => 'hugo_pascoal_@hotmail.com',
            'password' => Hash::make('123456'),
            'is_admin'=> 1
        ]);
    }
}
