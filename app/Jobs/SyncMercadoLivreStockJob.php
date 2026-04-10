<?php

namespace App\Jobs;

use App\Models\MercadoLivreListing;
use App\Models\ProductVariant;
use App\Services\MercadoLivreListingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMercadoLivreStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30; // segundos entre tentativas

    public function __construct(
        public readonly string $variantId,
        public readonly int $newStock,
    ) {}

    public function handle(MercadoLivreListingService $listingService): void
    {
        $variant = ProductVariant::find($this->variantId);

        if (!$variant) {
            return;
        }

        $listing = MercadoLivreListing::where('product_id', $variant->product_id)
            ->where('status', 'active')
            ->first();

        if (!$listing) {
            return; // produto não está publicado no ML
        }

        try {
            $listingService->syncVariantStock($listing, $variant);
        } catch (\Exception $e) {
            Log::error('ML: falha ao sincronizar estoque', [
                'variant_id' => $this->variantId,
                'sku'        => $variant->sku,
                'ml_item_id' => $listing->ml_item_id,
                'error'      => $e->getMessage(),
            ]);

            $listing->update(['last_error' => 'Falha ao sincronizar estoque: ' . $e->getMessage()]);

            throw $e; // requeue para nova tentativa
        }
    }
}
