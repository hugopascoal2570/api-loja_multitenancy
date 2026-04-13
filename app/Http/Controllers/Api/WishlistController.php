<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WishlistItemResource;
use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Lista todos os itens da lista de desejos do usuário autenticado.
     * GET /api/wishlist
     */
    public function index(Request $request): JsonResponse
    {
        $items = WishlistItem::with(['product.images'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'items' => WishlistItemResource::collection($items),
            'total' => $items->count(),
        ]);
    }

    /**
     * Adiciona um produto à lista de desejos.
     * POST /api/wishlist/{productId}
     */
    public function store(Request $request, string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $item = WishlistItem::firstOrCreate([
            'user_id'    => $request->user()->id,
            'product_id' => $product->id,
        ]);

        $item->load('product.images');

        return response()->json([
            'message' => 'Produto adicionado à lista de desejos.',
            'item'    => new WishlistItemResource($item),
        ], 201);
    }

    /**
     * Remove um produto da lista de desejos.
     * DELETE /api/wishlist/{productId}
     */
    public function destroy(Request $request, string $productId): JsonResponse
    {
        $deleted = WishlistItem::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Produto não encontrado na lista de desejos.',
            ], 404);
        }

        return response()->json([
            'message' => 'Produto removido da lista de desejos.',
        ]);
    }

    /**
     * Alterna um produto na lista de desejos (adiciona se não existir, remove se existir).
     * POST /api/wishlist/{productId}/toggle
     */
    public function toggle(Request $request, string $productId): JsonResponse
    {
        Product::findOrFail($productId);

        $existing = WishlistItem::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json([
                'message'      => 'Produto removido da lista de desejos.',
                'in_wishlist'  => false,
            ]);
        }

        $item = WishlistItem::create([
            'user_id'    => $request->user()->id,
            'product_id' => $productId,
        ]);

        $item->load('product.images');

        return response()->json([
            'message'     => 'Produto adicionado à lista de desejos.',
            'in_wishlist' => true,
            'item'        => new WishlistItemResource($item),
        ], 201);
    }

    /**
     * Verifica se um produto está na lista de desejos.
     * GET /api/wishlist/{productId}/check
     */
    public function check(Request $request, string $productId): JsonResponse
    {
        $inWishlist = WishlistItem::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->exists();

        return response()->json([
            'in_wishlist' => $inWishlist,
        ]);
    }
}
