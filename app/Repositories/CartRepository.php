<?php

namespace App\Repositories;

use App\Models\Cart;
use App\Models\CartItem;
use App\DTO\Cart\CartItemDTO;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\ProductKit;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;


class CartRepository
{
    public function findOrCreate(string $token): Cart
    {
        return Cart::firstOrCreate(['token' => $token], ['id' => Str::uuid()]);
    }

    public function addItem(Cart $cart, CartItemDTO $dto): CartItem
    {
        // Valida estoque se for variante
        if ($dto->variant_id) {
            $variant = ProductVariant::findOrFail($dto->variant_id);

            if ($variant->stock < $dto->quantity) {
                throw new \Exception("Estoque insuficiente. Disponível: {$variant->stock}, Solicitado: {$dto->quantity}");
            }
        }

        $unitPrice = $this->resolvePrice($dto, $dto->quantity);

        $item = $cart->items()->create([
            'product_id' => $dto->product_id,
            'type' => $dto->type,
            'variant_id' => $dto->variant_id,
            'kit_id' => $dto->kit_id,
            'quantity' => $dto->quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $dto->quantity,
        ]);

        // Recalcula preços de todos os itens do mesmo produto (atacado pode mudar)
        $this->recalculateProductPrices($cart, $dto->product_id);

        return $item->fresh(['product.images', 'variant', 'kit']);
    }

    /**
     * Resolve o preço unitário considerando atacado
     */
    private function resolvePrice(CartItemDTO $dto, int $totalProductQty = 1): float
    {
        if ($dto->type === 'kit' && $dto->kit_id) {
            $kit = ProductKit::findOrFail($dto->kit_id);
            return $kit->price;
        }

        $product = Product::findOrFail($dto->product_id);

        return $this->getProductUnitPrice($product, $totalProductQty);
    }

    /**
     * Determina o preço unitário de um produto baseado na quantidade
     *
     * Cenários:
     * - Só varejo (retail_price): usa varejo (ou promoção se ativa)
     * - Só atacado (wholesale_price): usa atacado sempre
     * - Ambos: usa atacado quando qty >= wholesale_min_qty, senão varejo
     */
    private function getProductUnitPrice(Product $product, int $totalQty): float
    {
        $hasRetail = $product->retail_price > 0;
        $hasWholesale = $product->wholesale_price > 0 && $product->wholesale_min_qty > 0;

        // Só atacado (sem varejo)
        if ($hasWholesale && !$hasRetail) {
            if ($product->is_promotion && $product->promotion_price && $product->promotion_price < $product->wholesale_price) {
                return $product->promotion_price;
            }
            return $product->wholesale_price;
        }

        // Só varejo (sem atacado)
        if ($hasRetail && !$hasWholesale) {
            return $product->is_promotion && $product->promotion_price
                ? $product->promotion_price
                : $product->retail_price;
        }

        // Ambos: verifica se atinge quantidade mínima de atacado
        if ($hasWholesale && $totalQty >= $product->wholesale_min_qty) {
            // Se tem promoção ativa e o preço promocional é menor que o atacado, usa o promocional
            if ($product->is_promotion && $product->promotion_price && $product->promotion_price < $product->wholesale_price) {
                return $product->promotion_price;
            }
            return $product->wholesale_price;
        }

        // Fallback: varejo com promoção
        return $product->is_promotion && $product->promotion_price
            ? $product->promotion_price
            : $product->retail_price;
    }

    /**
     * Recalcula os preços de todos os itens de um produto no carrinho
     * considerando a quantidade total para aplicar preço de atacado
     */
    private function recalculateProductPrices(Cart $cart, string $productId): void
    {
        $productItems = $cart->items()
            ->where('product_id', $productId)
            ->where('type', '!=', 'kit')
            ->get();

        $totalQty = $productItems->sum('quantity');

        $product = Product::find($productId);
        if (!$product) return;

        $unitPrice = $this->getProductUnitPrice($product, $totalQty);

        foreach ($productItems as $item) {
            $item->update([
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $item->quantity,
            ]);
        }
    }

    public function syncItems(Cart $cart, Collection $dtos): Collection
    {
        // Valida estoque antes de sincronizar
        foreach ($dtos as $dto) {
            if ($dto->variant_id) {
                $variant = ProductVariant::findOrFail($dto->variant_id);

                if ($variant->stock < $dto->quantity) {
                    throw new \Exception("Estoque insuficiente para variante {$variant->sku}. Disponível: {$variant->stock}, Solicitado: {$dto->quantity}");
                }
            }
        }

        $currentItems = $cart->items()->get();
        $matchedKeys = [];

        // Primeiro passo: sincronizar quantidades
        foreach ($dtos as $dto) {
            $existing = $currentItems->first(fn($item) =>
                $item->product_id === $dto->product_id &&
                $item->type === $dto->type &&
                $item->variant_id === $dto->variant_id &&
                $item->kit_id === $dto->kit_id
            );

            if ($existing) {
                $existing->quantity = $dto->quantity;
                $existing->save();
                $matchedKeys[] = $existing->id;
            } else {
                // Preço temporário - será recalculado abaixo
                $tempPrice = $this->resolvePrice($dto, $dto->quantity);
                $new = $cart->items()->create([
                    'product_id' => $dto->product_id,
                    'type' => $dto->type,
                    'variant_id' => $dto->variant_id,
                    'kit_id' => $dto->kit_id,
                    'quantity' => $dto->quantity,
                    'unit_price' => $tempPrice,
                    'total_price' => $dto->quantity * $tempPrice,
                ]);
                $matchedKeys[] = $new->id;
            }
        }

        // Remove itens que não foram enviados no sync
        $cart->items()->whereNotIn('id', $matchedKeys)->delete();

        // Segundo passo: recalcular preços por produto (considerando atacado)
        $productIds = $dtos->pluck('product_id')->unique();
        foreach ($productIds as $productId) {
            $this->recalculateProductPrices($cart, $productId);
        }

        return $cart->items()
            ->with(['product.images', 'variant', 'kit'])
            ->get();
    }

    public function getItems(Cart $cart)
    {
        return $cart->items()
            ->with(['product.images', 'variant', 'kit'])
            ->get();
    }


    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }

    public function removeItem(Cart $cart, int $itemId): void
    {
        $cart->items()->where('id', $itemId)->delete();
    }
}
