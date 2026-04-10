<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class ExportThemeSnapshot extends Command
{
    protected $signature   = 'theme:export-snapshot';
    protected $description = 'Gera um seeder com o snapshot atual dos settings de tema (home, product_detail)';

    public function handle(): void
    {
        $settings = Setting::whereIn('group', ['home', 'product_detail'])
            ->orderBy('group')
            ->orderBy('key')
            ->get();

        if ($settings->isEmpty()) {
            $this->error('Nenhum setting de tema encontrado no banco.');
            return;
        }

        $rows = $settings->map(function ($s) {
            $options = $s->options ? json_encode($s->options, JSON_UNESCAPED_UNICODE) : 'null';
            $value        = $this->phpStr($s->value);
            $defaultValue = $this->phpStr($s->default_value);
            $label        = $this->phpStr($s->label);
            $description  = $this->phpStr($s->description);

            return "            ['key' => '{$s->key}', 'group' => '{$s->group}', 'type' => '{$s->type}', 'value' => {$value}, 'default_value' => {$defaultValue}, 'label' => {$label}, 'description' => {$description}, 'options' => {$options}],";
        })->implode("\n");

        $content = <<<PHP
<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Snapshot gerado automaticamente em: {$this->now()}
 * Execute: php artisan db:seed --class=ThemeSnapshotSeeder
 */
class ThemeSnapshotSeeder extends Seeder
{
    public function run(): void
    {
        \$settings = [
{$rows}
        ];

        foreach (\$settings as \$data) {
            Setting::updateOrCreate(
                ['key' => \$data['key']],
                \$data
            );
        }

        \$this->command->info('✓ ' . count(\$settings) . ' configurações de tema aplicadas.');
    }
}
PHP;

        $path = database_path('seeders/ThemeSnapshotSeeder.php');
        file_put_contents($path, $content);

        $this->info("✓ Snapshot gerado em: database/seeders/ThemeSnapshotSeeder.php");
        $this->info("  Total de settings exportados: {$settings->count()}");
    }

    private function phpStr(?string $value): string
    {
        if ($value === null) return 'null';
        $escaped = str_replace("'", "\\'", $value);
        return "'{$escaped}'";
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }
}
