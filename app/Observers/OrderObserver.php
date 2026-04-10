<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\InventoryMovement;
use App\Services\InventoryMovementService;
use App\Services\KitStockRedistributionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function __construct(
        private InventoryMovementService $movementService,
        private KitStockRedistributionService $kitService,
    ) {}

    /**
     * Handle the Order "created" event.
     * Decrementa estoque se o pedido já nasce aprovado (ex: cartão aprovado imediatamente)
     */
    public function created(Order $order): void
    {
        Log::info('OrderObserver: Pedido criado', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
        ]);

        // Se o pedido já nasce com status "approved", decrementa o estoque
        if ($order->status === 'approved') {
            Log::info('OrderObserver: Pedido aprovado ao criar, decrementando estoque');
            $this->decrementStock($order);
        }
    }

    /**
     * Handle the Order "updating" event.
     * Gerencia o estoque automaticamente quando o status do pedido muda.
     */
    public function updating(Order $order): void
    {
        // Verifica se o status está mudando
        if (!$order->isDirty('status')) {
            return;
        }

        $oldStatus = $order->getOriginal('status');
        $newStatus = $order->status;

        Log::info('OrderObserver: Status do pedido mudando', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        // Quando o pedido é APROVADO → Decrementar estoque
        if ($newStatus === 'approved' && $oldStatus !== 'approved') {
            Log::info('OrderObserver: Pedido aprovado, decrementando estoque');
            $this->decrementStock($order);
        }

        // Quando o pedido é CANCELADO ou REEMBOLSADO (e estava em estado ativo) → Devolver estoque
        // Inclui cancellation_requested pois o estoque já foi decrementado quando aprovado
        $activeStatuses = ['approved', 'cancellation_requested', 'shipped', 'delivered'];
        if (in_array($newStatus, ['cancelled', 'refunded']) && in_array($oldStatus, $activeStatuses)) {
            Log::info('OrderObserver: Pedido cancelado/reembolsado, devolvendo estoque');
            $this->incrementStock($order);
        }
    }

    /**
     * Decrementa o estoque das variantes quando o pedido é aprovado
     */
    private function decrementStock(Order $order): void
    {
        // Verifica se já existe movimentação de saída para este pedido (evita duplicação)
        $existingMovement = InventoryMovement::where('related_order_id', $order->id)
            ->where('type', 'out')
            ->where('reason', 'sale')
            ->exists();

        if ($existingMovement) {
            Log::warning('OrderObserver: Estoque já foi decrementado para este pedido, ignorando', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
            return;
        }

        DB::transaction(function () use ($order) {
            $order->load('items.variant');

            foreach ($order->items as $item) {
                // Só processa itens que têm variant_id (produtos com variantes)
                if (!$item->variant_id || !$item->variant) {
                    continue;
                }

                $variant = $item->variant;
                $quantityOrdered = $item->quantity;
                $stockBefore = $variant->stock;

                // Verifica se há estoque suficiente
                if ($variant->stock < $quantityOrdered) {
                    Log::warning('Estoque insuficiente ao aprovar pedido', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'variant_id' => $variant->id,
                        'variant_sku' => $variant->sku,
                        'stock_available' => $variant->stock,
                        'quantity_ordered' => $quantityOrdered,
                    ]);

                    // Decrementa o que tem disponível (evita estoque negativo)
                    $quantityToDecrement = min($variant->stock, $quantityOrdered);
                    if ($quantityToDecrement > 0) {
                        $variant->decrement('stock', $quantityToDecrement);
                        $variant->refresh();

                        $this->movementService->recordMovement(
                            $variant,
                            'out',
                            $quantityToDecrement,
                            $stockBefore,
                            $variant->stock,
                            'sale',
                            $order->id,
                            "Venda parcial: {$quantityToDecrement} de {$quantityOrdered} unidades devido a estoque insuficiente",
                            auth()->id()
                        );
                    }
                } else {
                    // Estoque suficiente, decrementa normalmente
                    $variant->decrement('stock', $quantityOrdered);
                    $variant->refresh();

                    $this->movementService->recordMovement(
                        $variant,
                        'out',
                        $quantityOrdered,
                        $stockBefore,
                        $variant->stock,
                        'sale',
                        $order->id,
                        null,
                        auth()->id()
                    );
                }

                Log::info('Estoque decrementado', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'variant_id' => $variant->id,
                    'variant_sku' => $variant->sku,
                    'quantity_decremented' => $quantityOrdered,
                    'stock_before' => $stockBefore,
                    'stock_after' => $variant->stock,
                ]);
            }

            // Verificar redistribuição de kits após todas as decrementações
            foreach ($order->items as $item) {
                if ($item->variant_id && $item->variant) {
                    $item->variant->refresh();
                    $this->kitService->handleStockChange($item->variant);
                }
            }
        });
    }

    /**
     * Incrementa o estoque das variantes quando o pedido é cancelado/reembolsado
     */
    private function incrementStock(Order $order): void
    {
        // Verifica se já existe movimentação de devolução para este pedido (evita duplicação)
        $existingReturn = InventoryMovement::where('related_order_id', $order->id)
            ->where('type', 'in')
            ->whereIn('reason', ['cancellation', 'refund'])
            ->exists();

        if ($existingReturn) {
            Log::warning('OrderObserver: Estoque já foi devolvido para este pedido, ignorando', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
            return;
        }

        DB::transaction(function () use ($order) {
            $order->load('items.variant');

            foreach ($order->items as $item) {
                // Só processa itens que têm variant_id (produtos com variantes)
                if (!$item->variant_id || !$item->variant) {
                    continue;
                }

                $variant = $item->variant;
                $quantityToReturn = $item->quantity;
                $stockBefore = $variant->stock;

                // Devolve o estoque
                $variant->increment('stock', $quantityToReturn);
                $variant->refresh();

                // Determina a razão da devolução
                $reason = $order->status === 'cancelled' ? 'cancellation' : 'refund';

                $this->movementService->recordMovement(
                    $variant,
                    'in',
                    $quantityToReturn,
                    $stockBefore,
                    $variant->stock,
                    $reason,
                    $order->id,
                    null,
                    auth()->id()
                );

                Log::info('Estoque devolvido', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'variant_id' => $variant->id,
                    'variant_sku' => $variant->sku,
                    'quantity_returned' => $quantityToReturn,
                    'stock_before' => $stockBefore,
                    'stock_after' => $variant->stock,
                ]);
            }

            // Verificar restauração de kits após todas as devoluções
            foreach ($order->items as $item) {
                if ($item->variant_id && $item->variant) {
                    $item->variant->refresh();
                    $this->kitService->handleStockChange($item->variant);
                }
            }
        });
    }
}
