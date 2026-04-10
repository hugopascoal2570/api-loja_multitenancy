<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\FeaturedKitResource;
use App\Models\Product;
use App\Models\ProductKit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Busca kits em destaque
        $featuredKits = ProductKit::with(['product.images', 'items.variant'])
            ->featured()
            ->whereHas('product', function ($query) {
                $query->where('active', true);
            })
            ->get();

        return response()->json([
            'highlights' => $this->filteredProducts($request)->where('is_highlighted', true)->getResource(),
            'new' => $this->filteredProducts($request)->where('is_new', true)->getResource(),
            'new_collection' => $this->filteredProducts($request)->where('is_new_collection', true)->getResource(),
            'promotions' => $this->filteredProducts($request)->where('is_promotion', true)->getResource(),
            'featured_kits' => FeaturedKitResource::collection($featuredKits),
            'all' => $this->filteredProducts($request)->getResource(),
        ]);
    }

    protected function filteredProducts(Request $request)
    {
        $query = Product::with(['variants.images', 'images'])
            ->where('active', true);

            if ($request->filled('category')) {
                $query->whereHas('category', function ($q) use ($request) {
                    $q->where('slug', $request->category);
                });
            }            

        if ($request->filled('min_price')) {
            $query->where('retail_price', '>=', floatval($request->min_price));
        }

        if ($request->filled('max_price')) {
            $query->where('retail_price', '<=', floatval($request->max_price));
        }

        if ($request->filled('size')) {
            $query->whereHas('variants', function ($q) use ($request) {
                $q->where('size', $request->size);
            });
        }

        return new class($query) {
            public function __construct(private $query) {}

            public function where(...$args)
            {
                $this->query->where(...$args);
                return $this;
            }

            public function take($limit)
            {
                $this->query->take($limit);
                return $this;
            }

            public function getResource()
            {
                return ProductResource::collection($this->query->get());
            }
        };
    }

    public function show(string $slug): \Illuminate\Http\JsonResponse
    {
        $product = Product::with([
            'variants.images',
            'images',
            'kits' => fn($q) => $q->where('is_active', true)->with(['items.variant']),
            'category',
            'measurements'
        ])
        ->where('active', true)
        ->where('slug', $slug)
        ->firstOrFail();

        return response()->json(new ProductResource($product));
    }

    /**
     * Lista kits em destaque
     * GET /api/kits/featured
     */
    public function featuredKits(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 12);

        $kits = ProductKit::with(['product.images', 'items.variant'])
            ->featured()
            ->whereHas('product', function ($query) {
                $query->where('active', true);
            })
            ->paginate($perPage);

        return response()->json(FeaturedKitResource::collection($kits));
    }
}
