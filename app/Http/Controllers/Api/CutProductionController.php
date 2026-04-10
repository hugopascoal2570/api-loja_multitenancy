<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Production\StoreCutProductionRequest;
use App\Http\Resources\CutProductionResource;
use App\Models\Cut;
use App\Models\CutProduction;
use App\Repositories\CutProductionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CutProductionController extends Controller
{
    public function __construct(
        protected CutProductionRepository $repository
    ) {}

    public function index(Cut $cut)
    {
        $productions = $this->repository->findByCut($cut->id);
        return CutProductionResource::collection($productions);
    }

    public function store(StoreCutProductionRequest $request, Cut $cut): JsonResponse
    {
        $data = $request->validated();
        $data['cut_id'] = $cut->id;

        $production = $this->repository->store($data);

        return response()->json([
            'message' => 'Producao registrada com sucesso.',
            'data' => new CutProductionResource($production->load(['fabricRoll', 'product', 'productVariant'])),
        ], 201);
    }

    public function storeBatch(Request $request, Cut $cut): JsonResponse
    {
        $request->validate([
            'productions' => 'required|array|min:1',
            'productions.*.fabric_roll_id' => 'required|uuid|exists:fabric_rolls,id',
            'productions.*.product_id' => 'nullable|uuid|exists:products,id',
            'productions.*.product_variant_id' => 'nullable|uuid|exists:product_variants,id',
            'productions.*.product_description' => 'nullable|string|max:255',
            'productions.*.quantity_produced' => 'required|integer|min:1',
            'productions.*.fabric_meters_used' => 'nullable|numeric|min:0',
            'productions.*.notes' => 'nullable|string|max:500',
        ]);

        $items = collect($request->productions)->map(function ($item) use ($cut) {
            $item['cut_id'] = $cut->id;
            return $item;
        })->toArray();

        $productions = $this->repository->storeBatch($items);

        $loaded = collect($productions)->map(fn($p) => $p->load(['fabricRoll', 'product', 'productVariant']));

        return response()->json([
            'message' => count($productions) . ' producoes registradas com sucesso.',
            'data' => CutProductionResource::collection($loaded),
        ], 201);
    }

    public function show(CutProduction $cutProduction): CutProductionResource
    {
        return new CutProductionResource($this->repository->show($cutProduction->id));
    }

    public function update(StoreCutProductionRequest $request, CutProduction $cutProduction): JsonResponse
    {
        $updated = $this->repository->update($cutProduction, $request->validated());

        return response()->json([
            'message' => 'Producao atualizada com sucesso.',
            'data' => new CutProductionResource($updated),
        ]);
    }

    public function linkProduct(Request $request, CutProduction $cutProduction): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'product_variant_id' => 'nullable|uuid|exists:product_variants,id',
        ]);

        $updated = $this->repository->linkProduct(
            $cutProduction,
            $request->product_id,
            $request->product_variant_id
        );

        return response()->json([
            'message' => 'Produto vinculado com sucesso.',
            'data' => new CutProductionResource($updated),
        ]);
    }

    public function destroy(CutProduction $cutProduction): JsonResponse
    {
        $this->repository->destroy($cutProduction);
        return response()->json(['message' => 'Producao deletada com sucesso.']);
    }
}
