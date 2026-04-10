<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\BarcodeService;
use Illuminate\Http\JsonResponse;

class BarcodeController extends Controller
{
    public function __construct(private BarcodeService $barcodeService) {}

    /**
     * Busca uma variante pelo código de barras.
     * GET /api/barcode/{barcode}
     */
    public function lookup(string $barcode): JsonResponse
    {
        $variant = ProductVariant::with(['product.images', 'images'])
            ->where('barcode', $barcode)
            ->first();

        if (!$variant) {
            return response()->json(['message' => 'Código de barras não encontrado.'], 404);
        }

        $product = $variant->product;

        return response()->json([
            'variant_id' => $variant->id,
            'sku'        => $variant->sku,
            'barcode'    => $variant->barcode,
            'size'       => $variant->size,
            'color'      => $variant->color,
            'stock'      => $variant->stock,
            'product'    => [
                'id'                => $product->id,
                'name'              => $product->name,
                'is_promotion'      => (bool) $product->is_promotion,
                'retail_price'      => $product->retail_price,
                'promotion_price'   => $product->is_promotion ? $product->promotion_price : null,
                'promotion_percent' => $product->is_promotion ? $product->promotion_percent : null,
                'wholesale_price'   => $product->wholesale_price,
                'wholesale_min_qty' => $product->wholesale_min_qty,
            ],
        ]);
    }

    /**
     * Retorna SVG e PNG base64 do código de barras de uma variante.
     * GET /api/barcode/{barcode}/label
     */
    public function label(string $barcode): JsonResponse
    {
        $variant = ProductVariant::where('barcode', $barcode)->first();

        if (!$variant) {
            return response()->json(['message' => 'Código de barras não encontrado.'], 404);
        }

        return response()->json([
            'barcode' => $variant->barcode,
            'sku'     => $variant->sku,
            'svg'     => $this->barcodeService->toSvg($variant->barcode),
            'png'     => $this->barcodeService->toPngBase64($variant->barcode),
        ]);
    }

    /**
     * Gera/regenera o código de barras de uma variante específica.
     * POST /api/barcode/{variantId}/generate
     */
    public function generate(string $variantId): JsonResponse
    {
        $variant = ProductVariant::findOrFail($variantId);

        $variant->barcode = $this->barcodeService->generateUniqueBarcode();
        $variant->save();

        return response()->json([
            'variant_id' => $variant->id,
            'sku'        => $variant->sku,
            'barcode'    => $variant->barcode,
            'svg'        => $this->barcodeService->toSvg($variant->barcode),
        ]);
    }

    /**
     * Retorna etiquetas (SVG + PNG base64) de todas as variantes de um produto.
     * GET /api/products/{product}/barcode-labels
     */
    public function labelsForProduct(Product $product): JsonResponse
    {
        $variants = $product->variants()->whereNotNull('barcode')->get();

        if ($variants->isEmpty()) {
            return response()->json([
                'message' => 'Nenhuma variante com código de barras encontrada.',
                'labels'  => [],
            ]);
        }

        $labels = $variants->map(fn ($variant) => [
            'variant_id' => $variant->id,
            'sku'        => $variant->sku,
            'size'       => $variant->size,
            'color'      => $variant->color,
            'barcode'    => $variant->barcode,
            'svg'        => $this->barcodeService->toSvg($variant->barcode),
            'png'        => $this->barcodeService->toPngBase64($variant->barcode),
        ]);

        return response()->json([
            'product' => [
                'id'   => $product->id,
                'name' => $product->name,
            ],
            'labels' => $labels,
        ]);
    }

    /**
     * Gera códigos de barras para todas as variantes de um produto que ainda não têm.
     * POST /api/products/{product}/generate-barcodes
     */
    public function generateForProduct(Product $product): JsonResponse
    {
        $variants = $product->variants()->whereNull('barcode')->get();

        if ($variants->isEmpty()) {
            return response()->json([
                'message'   => 'Todas as variantes já possuem código de barras.',
                'generated' => 0,
            ]);
        }

        $generated = [];
        foreach ($variants as $variant) {
            $variant->barcode = $this->barcodeService->generateUniqueBarcode();
            $variant->save();

            $generated[] = [
                'variant_id' => $variant->id,
                'sku'        => $variant->sku,
                'barcode'    => $variant->barcode,
            ];
        }

        return response()->json([
            'message'   => count($generated) . ' código(s) de barras gerado(s) com sucesso.',
            'generated' => count($generated),
            'variants'  => $generated,
        ]);
    }
}
