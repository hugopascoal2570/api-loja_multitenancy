<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,  // Deve rodar ANTES dos seeders que dependem de usuários
            CategorySeeder::class,
            ProductSeeder::class,
            PermissionSeeder::class,
            UserSeeder::class,
            AssignAllPermissionsToAdminSeeder::class,
            DeliverySettingSeeder::class,
        ]);
    }
}
