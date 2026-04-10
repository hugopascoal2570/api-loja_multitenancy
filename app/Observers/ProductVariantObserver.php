<?php

namespace App\Observers;

use App\Models\ProductVariant;

class ProductVariantObserver
{
    /**
     * Quando uma variante é soft-deletada, soft-deleta seus movimentos de estoque também.
     * Isso garante que o histórico não suma mas fique marcado como removido.
     */
    public function deleted(ProductVariant $variant): void
    {
        if ($variant->isForceDeleting()) {
            // Force delete: hard-deleta os movimentos junto
            $variant->movements()->forceDelete();
        } else {
            // Soft delete: preserva os movimentos mas os marca como deletados
            $variant->movements()->delete();
        }
    }

    /**
     * Quando uma variante é restaurada, restaura seus movimentos também.
     */
    public function restored(ProductVariant $variant): void
    {
        $variant->movements()->withTrashed()->restore();
    }
}
