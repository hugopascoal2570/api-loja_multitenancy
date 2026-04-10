<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            // ── Plataforma ────────────────────────────────────────────────────
            [
                'name'         => Role::PLATFORM_SUPER_ADMIN,
                'display_name' => 'Super Admin da Plataforma',
                'guard'        => 'platform',
                'description'  => 'Acesso irrestrito a todos os tenants e configurações da plataforma.',
            ],

            // ── Tenant ────────────────────────────────────────────────────────
            [
                'name'         => Role::TENANT_OWNER,
                'display_name' => 'Dono da Loja',
                'guard'        => 'tenant',
                'description'  => 'Acesso completo à loja. Pode gerenciar funcionários, integrações e configurações.',
            ],
            [
                'name'         => Role::TENANT_MANAGER,
                'display_name' => 'Gerente',
                'guard'        => 'tenant',
                'description'  => 'Acesso administrativo completo, exceto configurações críticas (integrações, plano).',
            ],
            [
                'name'         => Role::TENANT_EMPLOYEE,
                'display_name' => 'Funcionário',
                'guard'        => 'tenant',
                'description'  => 'Acesso parcial ao painel admin. Permissões específicas definidas individualmente.',
            ],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );
        }

        $this->command->info('Roles criados: ' . implode(', ', array_column($roles, 'name')));
    }
}
