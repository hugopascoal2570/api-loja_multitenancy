<?php

namespace App\Http\Controllers\Api;

use App\DTO\Product\ProductDTO;
use App\Http\Requests\Api\Product\StoreProductRequest;
use App\Http\Requests\Api\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Repositories\ProductRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    public function __construct(
        protected ProductRepository $repository
    ) {}

    public function index(Request $request)
    {
        if (filter_var($request->query('all'), FILTER_VALIDATE_BOOLEAN)) {
            $products = $this->repository->getAll();
        } else {
            $products = $this->repository->paginate();
        }

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $dto = ProductDTO::fromRequest($request);
        $product = $this->repository->storeFromDTO($dto);

        return response()->json(new ProductResource($product), 201);
    }

    public function show(string $id): ProductResource
    {
        return new ProductResource($this->repository->show($id));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $dto = ProductDTO::fromRequest($request);
        $product = $this->repository->update($product, $dto);

        return response()->json(new ProductResource($product));
    }

    /**
     * Duplica um produto completo (variantes, imagens, kits)
     * POST /api/products/{product}/duplicate
     */
    public function duplicate(Product $product): JsonResponse
    {
        $duplicatedProduct = $this->repository->duplicate($product);

        return response()->json([
            'message' => 'Produto duplicado com sucesso.',
            'original_product' => $product->name,
            'duplicated_product' => $duplicatedProduct->name,
            'data' => new ProductResource($duplicatedProduct)
        ], 201);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->repository->destroy($product);
        return response()->json(['message' => 'Produto deletado com sucesso.']);
    }

    /**
     * Exibe um produto ativo pelo slug (rota pública)
     * GET /api/products/{slug}/show
     */
    public function showBySlug(string $slug): ProductResource
    {
        return new ProductResource($this->repository->showBySlug($slug));
    }

    /**
     * Lista produtos em promoção (rota pública)
     * GET /api/products/promotions
     */
    public function promotions(Request $request)
    {
        $perPage = $request->integer('per_page', 15);

        $products = Product::with(['images', 'category'])
            ->where('active', true)
            ->where('is_promotion', true)
            ->paginate($perPage);

        return ProductResource::collection($products);
    }

    /**
     * Lista produtos ativos (rota pública)
     * GET /api/products/public?all=true
     */
    public function publicList(Request $request)
    {
        if (filter_var($request->query('all'), FILTER_VALIDATE_BOOLEAN)) {
            $products = $this->repository->getAll();
        } else {
            $perPage = $request->integer('per_page', 15);
            $products = Product::with(['variants.images', 'images', 'category'])
                ->where('active', true)
                ->paginate($perPage);
        }

        return ProductResource::collection($products);
    }
}
