<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CleanLegacyThemeSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Remove os grupos legados (estrutura antiga)
        $legacyGroups = ['theme', 'hero', 'sections', 'trust', 'newsletter', 'footer'];

        $deleted = Setting::whereIn('group', $legacyGroups)->forceDelete();

        $this->command->info("✓ {$deleted} configuração(ões) legada(s) removida(s).");

        // 2. Sincroniza default_value = value em todos os settings de tema atuais
        $synced = Setting::whereIn('group', ['home', 'product_detail'])
            ->update(['default_value' => DB::raw('value')]);

        $this->command->info("✓ {$synced} valor(es) padrão sincronizado(s) com o estado atual.");
    }
}
