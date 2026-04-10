<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Coupon;
use Carbon\Carbon;

class DeactivateExpiredCoupons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coupons:deactivate-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Desativa cupons com data de validade expirada';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Verificando cupons expirados...');

        $now = Carbon::now()->startOfDay();

        // Busca cupons ativos que já expiraram
        $expiredCoupons = Coupon::where('is_active', true)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', $now)
            ->get();

        if ($expiredCoupons->isEmpty()) {
            $this->info('Nenhum cupom expirado encontrado.');
            return Command::SUCCESS;
        }

        $count = $expiredCoupons->count();
        $this->info("Encontrados {$count} cupom(ns) expirado(s).");

        // Inativa os cupons
        foreach ($expiredCoupons as $coupon) {
            $coupon->is_active = false;
            $coupon->save();
            $this->line("✓ Cupom '{$coupon->code}' desativado (expirou em {$coupon->valid_until->format('d/m/Y')})");
        }

        $this->info("Total de {$count} cupom(ns) desativado(s) com sucesso!");

        return Command::SUCCESS;
    }
}
