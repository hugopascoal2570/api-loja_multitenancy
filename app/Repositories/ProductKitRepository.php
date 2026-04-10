<?php

namespace App\Repositories;

use App\Models\ProductKit;
use App\Models\ProductKitItem;
use Illuminate\Support\Facades\DB;

class ProductKitRepository
{
    public function create($product, array $data)
    {
        return DB::transaction(function () use ($product, $data) {
            $kit = ProductKit::create([
                'product_id' => $product->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'total_quantity' => $data['total_quantity'],
                'fixed_color' => $data['fixed_color'] ?? null,
                'fixed_size' => $data['fixed_size'] ?? null,
                'is_featured' => $data['is_featured'] ?? false,
                'weight' => $data['weight'] ?? null,
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null,
                'length' => $data['length'] ?? null,
            ]);

            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    ProductKitItem::create([
                        'product_kit_id' => $kit->id,
                        'variant_id' => $item['variant_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }
            }

            return $kit->load('items.variant');
        });
    }

    public function update(ProductKit $kit, array $data): ProductKit
    {
        return DB::transaction(function () use ($kit, $data) {
            $kit->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'total_quantity' => $data['total_quantity'],
                'fixed_color' => $data['fixed_color'] ?? null,
                'fixed_size' => $data['fixed_size'] ?? null,
                'is_featured' => $data['is_featured'] ?? false,
                'weight' => $data['weight'] ?? null,
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null,
                'length' => $data['length'] ?? null,
                // Reset redistribuição ao atualizar manualmente
                'is_redistributed' => false,
                'is_active' => true,
                'redistributed_at' => null,
            ]);

            // Limpar snapshot original (admin definiu nova configuração)
            $kit->originalItems()->delete();

            // Atualiza os itens
            $kit->items()->delete();

            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                        ProductKitItem::create([
                            'product_kit_id' => $kit->id,
                            'variant_id' => $item['variant_id'],
                            'quantity' => $item['quantity'],
                        ]);
                }
            }

            return $kit->load('items.variant');
        });
    }

    public function destroy(ProductKit $kit): void
    {
        DB::transaction(function () use ($kit) {
            $kit->originalItems()->delete();
            $kit->items()->delete();
            $kit->delete();
        });
    }
}
