<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\KitStockRedistributionService;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    /**
     * Adiciona uma nova variante ao produto.
     */
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'size' => 'required|string|max:10',
            'color' => 'required|string|max:50',
            'stock' => 'required|integer|min:0',
            'sku' => 'nullable|string|max:50',
        ]);

        $variant = $product->variants()->create($validated);

        return response()->json([
            'message' => 'Variante adicionada com sucesso',
            'data' => $variant,
        ], 201);
    }

    /**
     * Atualiza uma variante existente.
     */
    public function update(Request $request, Product $product, ProductVariant $variant)
    {
        // Verifica se a variante pertence ao produto
        if ($variant->product_id !== $product->id) {
            return response()->json([
                'message' => 'Variante não pertence a este produto',
            ], 403);
        }

        $validated = $request->validate([
            'size' => 'sometimes|required|string|max:10',
            'color' => 'sometimes|required|string|max:50',
            'stock' => 'sometimes|required|integer|min:0',
            'sku' => 'nullable|string|max:50',
        ]);

        $variant->update($validated);

        // Verificar redistribuição de kits se o estoque foi alterado
        if (array_key_exists('stock', $validated)) {
            $variant->refresh();
            app(KitStockRedistributionService::class)->handleStockChange($variant);
        }

        return response()->json([
            'message' => 'Variante atualizada com sucesso',
            'data' => $variant,
        ]);
    }

    /**
     * Remove uma variante do produto.
     */
    public function destroy(Product $product, ProductVariant $variant)
    {
        // Verifica se a variante pertence ao produto
        if ($variant->product_id !== $product->id) {
            return response()->json([
                'message' => 'Variante não pertence a este produto',
            ], 403);
        }

        $variant->delete();

        return response()->json([
            'message' => 'Variante removida com sucesso',
        ]);
    }
}
