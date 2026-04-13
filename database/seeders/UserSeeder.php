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

        $superAdmins = [
            ['name' => 'Hugo Pascoal',  'email' => 'hugo_pascoal_@hotmail.com'],
            ['name' => 'Hugo',          'email' => 'hugo159hb@gmail.com'],
            ['name' => 'CloChic Admin', 'email' => 'clochicbrand@gmail.com'],
        ];

        foreach ($superAdmins as $admin) {
            User::updateOrCreate(
                ['email' => $admin['email']],
                [
                    'name'     => $admin['name'],
                    'password' => Hash::make('123456'),
                    'is_admin' => 1,
                ]
            );
        }
    }
}
