<?php

namespace App\Console\Commands;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixDuplicateInventoryMovements extends Command
{
    protected $signature = 'inventory:fix-duplicates {--dry-run : Apenas mostra o que seria corrigido sem executar}';
    protected $description = 'Corrige movimentações de estoque duplicadas e ajusta o estoque das variantes';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 Modo DRY-RUN: Apenas mostrando o que seria corrigido...');
        }

        $this->info('Buscando pedidos com movimentações duplicadas...');

        // Busca pedidos que têm mais de uma movimentação de saída por venda
        $duplicates = InventoryMovement::select('related_order_id', 'product_variant_id')
            ->where('type', 'out')
            ->where('reason', 'sale')
            ->whereNotNull('related_order_id')
            ->groupBy('related_order_id', 'product_variant_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('✅ Nenhuma duplicação encontrada!');
            return 0;
        }

        $this->warn("Encontradas {$duplicates->count()} combinações de pedido/variante com duplicação.");

        $totalMovementsRemoved = 0;
        $totalStockRestored = 0;

        DB::beginTransaction();

        try {
            foreach ($duplicates as $duplicate) {
                $orderId = $duplicate->related_order_id;
                $variantId = $duplicate->product_variant_id;

                // Busca todas as movimentações duplicadas para este pedido/variante
                $movements = InventoryMovement::where('related_order_id', $orderId)
                    ->where('product_variant_id', $variantId)
                    ->where('type', 'out')
                    ->where('reason', 'sale')
                    ->orderBy('created_at', 'asc')
                    ->get();

                // Mantém a primeira movimentação, remove as demais
                $first = $movements->shift();
                $duplicatesToRemove = $movements;

                $sku = $first->variant->sku ?? 'N/A';
                $this->info("\n📦 Pedido ID: {$orderId}");
                $this->info("   Variante ID: {$variantId} (SKU: {$sku})");
                $this->info("   Movimentação original: {$first->created_at} - Qtd: {$first->quantity}");

                $quantityToRestore = 0;

                foreach ($duplicatesToRemove as $movement) {
                    $this->warn("   ❌ Duplicada: {$movement->created_at} - Qtd: {$movement->quantity} - ID: {$movement->id}");
                    $quantityToRestore += $movement->quantity;
                    $totalMovementsRemoved++;

                    if (!$dryRun) {
                        $movement->delete();
                    }
                }

                // Restaura o estoque da variante
                if ($quantityToRestore > 0) {
                    $variant = ProductVariant::find($variantId);
                    if ($variant) {
                        $stockBefore = $variant->stock;
                        $stockAfter = $stockBefore + $quantityToRestore;

                        $this->info("   🔄 Restaurando estoque: {$stockBefore} → {$stockAfter} (+{$quantityToRestore})");

                        if (!$dryRun) {
                            $variant->increment('stock', $quantityToRestore);
                        }

                        $totalStockRestored += $quantityToRestore;
                    }
                }
            }

            if (!$dryRun) {
                DB::commit();
                $this->newLine();
                $this->info("✅ Correção concluída!");
                $this->info("   - Movimentações removidas: {$totalMovementsRemoved}");
                $this->info("   - Unidades de estoque restauradas: {$totalStockRestored}");
            } else {
                DB::rollBack();
                $this->newLine();
                $this->warn("🔍 DRY-RUN concluído. Nenhuma alteração foi feita.");
                $this->info("   - Movimentações que seriam removidas: {$totalMovementsRemoved}");
                $this->info("   - Unidades de estoque que seriam restauradas: {$totalStockRestored}");
                $this->newLine();
                $this->info("Execute sem --dry-run para aplicar as correções.");
            }

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Erro ao corrigir duplicações: {$e->getMessage()}");
            return 1;
        }
    }
}
