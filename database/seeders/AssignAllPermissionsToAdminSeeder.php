<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssignAllPermissionsToAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'hugo_pascoal_@hotmail.com')->first();

        if (!$user) {
            $this->command->warn('Usuário admin não encontrado. Rode o UserSeeder antes.');
            return;
        }

        // pega todos os IDs da tabela permissions (UUIDs)
        $permissionIds = DB::table('permissions')->pluck('id')->all();

        // anexa sem duplicar
        $user->permissions()->syncWithoutDetaching($permissionIds);

        $this->command->info('Todas as permissões foram atribuídas ao usuário admin.');
    }
}
