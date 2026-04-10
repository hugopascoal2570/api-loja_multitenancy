<?php

namespace App\Http\Controllers\Api;

use App\DTO\Category\CategoryDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Category\StoreCategoryRequest;
use App\Http\Requests\Api\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryRepository $repository
    ) {}

    public function index(): JsonResponse
    {
        $categories = $this->repository->all();
        return response()->json(CategoryResource::collection($categories));
    }

    /**
     * Lista todas as categorias com seus produtos (rota pública)
     * GET /api/categories/public
     */
    public function publicList(): JsonResponse
    {
        $categories = Category::with(['products' => function ($query) {
            $query->where('active', true)
                  ->with('images');
        }])->get();

        return response()->json($categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'products' => ProductResource::collection($category->products),
            ];
        }));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $dto = new CategoryDTO(...$request->validated());
        $category = $this->repository->store($dto);

        return response()->json(new CategoryResource($category), 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json(new CategoryResource($category));
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $dto = new CategoryDTO(...$request->validated());
        $category = $this->repository->update($category, $dto);

        return response()->json(new CategoryResource($category));
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->repository->destroy($category);

        return response()->json(['message' => 'Categoria deletada com sucesso.']);
    }
}
