<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductMeasurementResource;
use App\Models\MercadoLivreListing;
use App\Models\Product;
use App\Models\ProductMeasurement;
use App\Services\MercadoLivreListingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductMeasurementController extends Controller
{
    private function measurementRules(string $prefix = ''): array
    {
        $fields = ['bust', 'waist', 'hip', 'waistband', 'rise', 'inseam', 'thigh', 'length', 'shoulder', 'sleeve'];
        $rules = [
            $prefix . 'size' => 'required|string|max:10',
            $prefix . 'sort_order' => 'nullable|integer|min:0',
        ];
        foreach ($fields as $field) {
            $rules[$prefix . $field] = 'nullable|numeric|min:0';
        }
        return $rules;
    }

    public function index(Product $product)
    {
        $measurements = $product->measurements()->orderBy('sort_order')->get();

        return ProductMeasurementResource::collection($measurements);
    }

    public function store(Request $request, Product $product)
    {
        $validated = $request->validate($this->measurementRules());

        $exists = $product->measurements()->where('size', $validated['size'])->exists();
        if ($exists) {
            return response()->json([
                'message' => "Já existe uma medida para o tamanho '{$validated['size']}' neste produto."
            ], 422);
        }

        $measurement = $product->measurements()->create($validated);

        return (new ProductMeasurementResource($measurement))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, ProductMeasurement $measurement)
    {
        $validated = $request->validate($this->measurementRules());

        $exists = ProductMeasurement::where('product_id', $measurement->product_id)
            ->where('size', $validated['size'])
            ->where('id', '!=', $measurement->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => "Já existe uma medida para o tamanho '{$validated['size']}' neste produto."
            ], 422);
        }

        $measurement->update($validated);

        return new ProductMeasurementResource($measurement);
    }

    public function destroy(ProductMeasurement $measurement)
    {
        $measurement->delete();

        return response()->json(['message' => 'Medida removida com sucesso.']);
    }

    public function bulkStore(Request $request, Product $product)
    {
        $validated = $request->validate([
            'measurements' => 'required|array|min:1',
            ...$this->measurementRules('measurements.*.'),
        ]);

        $product->measurements()->delete();

        $created = [];
        foreach ($validated['measurements'] as $index => $data) {
            $data['sort_order'] = $data['sort_order'] ?? $index;
            $created[] = $product->measurements()->create($data);
        }

        // Sincroniza chart da peça no ML se o produto estiver publicado
        $listing = MercadoLivreListing::where('product_id', $product->id)
            ->whereIn('status', ['active', 'paused'])
            ->first();

        if ($listing) {
            try {
                $product->load(['variants', 'images', 'measurements']);
                $mlService   = app(MercadoLivreListingService::class);
                $pieceChartId = $mlService->syncPieceSizeChart($product, $listing->ml_piece_chart_id);

                if ($pieceChartId) {
                    $listing->update(['ml_piece_chart_id' => $pieceChartId]);
                    Log::info('ML: piece chart sincronizado via bulk measurements', [
                        'product_id' => $product->id,
                        'chart_id'   => $pieceChartId,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('ML: falha ao sincronizar piece chart via bulk measurements', [
                    'product_id' => $product->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return ProductMeasurementResource::collection(collect($created));
    }

    public function uploadImage(Request $request, Product $product)
    {
        $request->validate([
            'measurement_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        // Remove imagem anterior se existir
        if ($product->measurement_image) {
            $oldPath = str_replace('/storage/', '', $product->measurement_image);
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('measurement_image')->store('products', 'public');
        $url = Storage::url($path);
        $product->update(['measurement_image' => $url]);

        return response()->json([
            'message' => 'Imagem da tabela de medidas atualizada com sucesso.',
            'measurement_image' => $url,
        ]);
    }

    public function deleteImage(Product $product)
    {
        if ($product->measurement_image) {
            $oldPath = str_replace('/storage/', '', $product->measurement_image);
            Storage::disk('public')->delete($oldPath);
            $product->update(['measurement_image' => null]);
        }

        return response()->json(['message' => 'Imagem da tabela de medidas removida.']);
    }
}
