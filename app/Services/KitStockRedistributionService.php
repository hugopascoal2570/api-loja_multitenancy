<?php

namespace App\Services;

use App\Models\ProductKit;
use App\Models\ProductKitItem;
use App\Models\ProductKitItemOriginal;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KitStockRedistributionService
{
    /**
     * Ponto de entrada principal: chamado após qualquer mudança de estoque em uma variante.
     */
    public function handleStockChange(ProductVariant $variant): void
    {
        $variant->refresh();

        $affectedKits = $this->findAffectedKits($variant);

        if ($affectedKits->isEmpty()) {
            return;
        }

        foreach ($affectedKits as $kit) {
            DB::transaction(function () use ($kit, $variant) {
                // Lock para evitar condições de corrida
                $kit = ProductKit::lockForUpdate()->find($kit->id);
                if (!$kit) {
                    return;
                }

                if ($variant->stock <= 0) {
                    $this->handleStockDepleted($kit, $variant);
                } elseif ($variant->stock > 0 && $kit->is_redistributed) {
                    $this->attemptRestore($kit);
                }
            });
        }
    }

    /**
     * Encontra todos os kits afetados por esta variante.
     * Busca nos itens atuais E nos itens originais (para kits já redistribuídos).
     */
    private function findAffectedKits(ProductVariant $variant): Collection
    {
        // Kits que contêm esta variante nos itens atuais
        $currentKitIds = ProductKitItem::where('variant_id', $variant->id)
            ->pluck('product_kit_id');

        // Kits que continham esta variante nos itens originais (já foram redistribuídos)
        $originalKitIds = ProductKitItemOriginal::where('variant_id', $variant->id)
            ->pluck('product_kit_id');

        $allKitIds = $currentKitIds->merge($originalKitIds)->unique();

        return ProductKit::whereIn('id', $allKitIds)->get();
    }

    /**
     * Trata quando o estoque de uma variante chega a zero.
     */
    private function handleStockDepleted(ProductKit $kit, ProductVariant $outOfStockVariant): void
    {
        // Verificar se esta variante está nos itens atuais do kit
        $kitItem = $kit->items()->where('variant_id', $outOfStockVariant->id)->first();
        if (!$kitItem) {
            return; // Variante já foi removida deste kit
        }

        // Salvar snapshot original na primeira redistribuição
        if (!$kit->is_redistributed) {
            $this->saveOriginalSnapshot($kit);
        }

        // Carregar itens restantes com estoque das variantes
        $remainingItems = $kit->items()
            ->where('variant_id', '!=', $outOfStockVariant->id)
            ->with('variant')
            ->get();

        // Se todos os restantes também estão sem estoque → desativar kit
        $itemsWithStock = $remainingItems->filter(fn($item) => $item->variant && $item->variant->stock > 0);

        if ($itemsWithStock->isEmpty()) {
            $this->deactivateKit($kit);
            return;
        }

        $quantityToRedistribute = $kitItem->quantity;

        // Montar dados para redistribuição
        $itemsForRedistribution = $remainingItems->map(fn($item) => [
            'variant_id' => $item->variant_id,
            'current_quantity' => $item->quantity,
            'variant_stock' => $item->variant ? $item->variant->stock : 0,
        ])->toArray();

        // Executar redistribuição
        $newDistribution = $this->distributeQuantity($itemsForRedistribution, $quantityToRedistribute);

        // Aplicar: deletar itens atuais e recriar com nova distribuição
        $kit->items()->delete();

        foreach ($newDistribution as $entry) {
            ProductKitItem::create([
                'product_kit_id' => $kit->id,
                'variant_id' => $entry['variant_id'],
                'quantity' => $entry['new_quantity'],
            ]);
        }

        $kit->update([
            'is_redistributed' => true,
            'is_active' => true,
            'redistributed_at' => now(),
        ]);

        Log::info('Kit redistribuído automaticamente', [
            'kit_id' => $kit->id,
            'kit_name' => $kit->name,
            'variante_removida' => $outOfStockVariant->id,
            'variante_size' => $outOfStockVariant->size,
            'quantidade_redistribuida' => $quantityToRedistribute,
        ]);
    }

    /**
     * Salva a configuração original do kit antes da primeira redistribuição.
     */
    private function saveOriginalSnapshot(ProductKit $kit): void
    {
        // Só salva se não existe snapshot ainda
        if ($kit->originalItems()->exists()) {
            return;
        }

        $kit->items->each(function ($item) use ($kit) {
            ProductKitItemOriginal::create([
                'product_kit_id' => $kit->id,
                'variant_id' => $item->variant_id,
                'quantity' => $item->quantity,
            ]);
        });

        Log::info('Snapshot original do kit salvo', [
            'kit_id' => $kit->id,
            'itens' => $kit->items->map(fn($i) => [
                'variant_id' => $i->variant_id,
                'quantity' => $i->quantity,
            ])->toArray(),
        ]);
    }

    /**
     * Tenta restaurar a configuração original do kit.
     */
    private function attemptRestore(ProductKit $kit): void
    {
        $originals = $kit->originalItems()->with('variant')->get();

        if ($originals->isEmpty()) {
            return;
        }

        // Verificar quais variantes originais ainda estão sem estoque
        $outOfStockOriginals = $originals->filter(fn($orig) => !$orig->variant || $orig->variant->stock <= 0);

        if ($outOfStockOriginals->isEmpty()) {
            // RESTAURAÇÃO COMPLETA: todas as variantes têm estoque
            $kit->items()->delete();

            foreach ($originals as $original) {
                ProductKitItem::create([
                    'product_kit_id' => $kit->id,
                    'variant_id' => $original->variant_id,
                    'quantity' => $original->quantity,
                ]);
            }

            // Limpar snapshot e resetar flags
            $kit->originalItems()->delete();
            $kit->update([
                'is_redistributed' => false,
                'is_active' => true,
                'redistributed_at' => null,
            ]);

            Log::info('Kit restaurado à configuração original', ['kit_id' => $kit->id]);
        } else {
            // RESTAURAÇÃO PARCIAL: restaurar original e re-redistribuir para variantes sem estoque
            $kit->items()->delete();

            foreach ($originals as $original) {
                ProductKitItem::create([
                    'product_kit_id' => $kit->id,
                    'variant_id' => $original->variant_id,
                    'quantity' => $original->quantity,
                ]);
            }

            // Re-redistribuir para cada variante que ainda está sem estoque
            foreach ($outOfStockOriginals as $outOfStockOriginal) {
                $kit->refresh();
                $kit->load('items');

                $kitItem = $kit->items()->where('variant_id', $outOfStockOriginal->variant_id)->first();
                if (!$kitItem) {
                    continue;
                }

                $remainingItems = $kit->items()
                    ->where('variant_id', '!=', $outOfStockOriginal->variant_id)
                    ->with('variant')
                    ->get();

                $itemsWithStock = $remainingItems->filter(fn($item) => $item->variant && $item->variant->stock > 0);

                if ($itemsWithStock->isEmpty()) {
                    $this->deactivateKit($kit);
                    return;
                }

                $quantityToRedistribute = $kitItem->quantity;

                $itemsForRedistribution = $remainingItems->map(fn($item) => [
                    'variant_id' => $item->variant_id,
                    'current_quantity' => $item->quantity,
                    'variant_stock' => $item->variant ? $item->variant->stock : 0,
                ])->toArray();

                $newDistribution = $this->distributeQuantity($itemsForRedistribution, $quantityToRedistribute);

                $kit->items()->delete();
                foreach ($newDistribution as $entry) {
                    ProductKitItem::create([
                        'product_kit_id' => $kit->id,
                        'variant_id' => $entry['variant_id'],
                        'quantity' => $entry['new_quantity'],
                    ]);
                }
            }

            $kit->update([
                'is_redistributed' => true,
                'is_active' => true,
                'redistributed_at' => now(),
            ]);

            Log::info('Kit parcialmente restaurado com redistribuição', ['kit_id' => $kit->id]);
        }
    }

    /**
     * Algoritmo de redistribuição: round-robin priorizando variantes com mais estoque.
     *
     * @param array $remainingItems [{variant_id, current_quantity, variant_stock}]
     * @param int $quantityToDistribute
     * @return array [{variant_id, new_quantity}]
     */
    private function distributeQuantity(array $remainingItems, int $quantityToDistribute): array
    {
        // Separar itens com estoque e sem estoque
        $withStock = collect($remainingItems)->filter(fn($item) => $item['variant_stock'] > 0);
        $withoutStock = collect($remainingItems)->filter(fn($item) => $item['variant_stock'] <= 0);

        // Peças de variantes sem estoque também entram no pool de redistribuição
        foreach ($withoutStock as $zeroItem) {
            $quantityToDistribute += $zeroItem['current_quantity'];
        }

        if ($withStock->isEmpty()) {
            return []; // Todas sem estoque, caller deve desativar kit
        }

        // Ordenar por estoque DESC
        $sorted = $withStock->sortByDesc('variant_stock')->values();

        // Inicializar resultado com quantidades atuais
        $result = $sorted->map(fn($item) => [
            'variant_id' => $item['variant_id'],
            'new_quantity' => $item['current_quantity'],
        ])->toArray();

        // Distribuir round-robin: 1 peça por vez, começando pelo maior estoque
        $remaining = $quantityToDistribute;
        while ($remaining > 0) {
            $distributedThisRound = false;
            foreach ($result as &$item) {
                if ($remaining <= 0) {
                    break;
                }
                $item['new_quantity']++;
                $remaining--;
                $distributedThisRound = true;
            }
            unset($item);

            if (!$distributedThisRound) {
                break; // Segurança contra loop infinito
            }
        }

        return $result;
    }

    /**
     * Desativa um kit quando TODAS as variantes ficam sem estoque.
     */
    private function deactivateKit(ProductKit $kit): void
    {
        $kit->update([
            'is_active' => false,
            'is_redistributed' => true,
            'redistributed_at' => now(),
        ]);

        Log::warning('Kit desativado - todas as variantes sem estoque', [
            'kit_id' => $kit->id,
            'kit_name' => $kit->name,
        ]);
    }
}
