<?php

namespace App\Observers;

use App\Jobs\SyncMercadoLivreStockJob;
use App\Models\InventoryMovement;

class InventoryMovementObserver
{
    public function created(InventoryMovement $movement): void
    {
        // Dispara sync assíncrono ao ML sempre que o estoque mudar
        SyncMercadoLivreStockJob::dispatch(
            $movement->product_variant_id,
            $movement->stock_after,
        )->delay(now()->addSeconds(2)); // pequeno delay para garantir que o DB commitou
    }
}
