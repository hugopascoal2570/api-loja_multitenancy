<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompletePaidCarts extends Command
{
    protected $signature = 'carts:complete-paid';
    protected $description = 'Marca como completed os carts cujas orders já estão approved';

    public function handle(): void
    {
        $updated = DB::affectingStatement("
            UPDATE carts c
            JOIN orders o ON o.cart_id = c.id
            SET c.status = 'completed', c.updated_at = NOW()
            WHERE o.status = 'approved' AND c.status = 'pending'
        ");

        Log::info("[CompletePaidCarts] carts atualizados: {$updated}");
        $this->info("Carts atualizados: {$updated}");
    }
}
