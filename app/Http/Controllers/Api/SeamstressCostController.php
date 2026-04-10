<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SeamstressCostResource;
use App\Models\Seamstress;
use App\Models\SeamstressCost;
use App\Repositories\SeamstressCostRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeamstressCostController extends Controller
{
    public function __construct(
        protected SeamstressCostRepository $repository
    ) {}

    public function index(Seamstress $seamstress)
    {
        $costs = $this->repository->findBySeamstress($seamstress->id);
        return SeamstressCostResource::collection($costs);
    }

    public function store(Request $request, Seamstress $seamstress): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'cost_type' => 'nullable|in:per_piece,fixed',
            'is_active' => 'nullable|in:true,false,1,0',
            'notes' => 'nullable|string|max:255',
        ]);

        $data['seamstress_id'] = $seamstress->id;
        $data['is_active'] = filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $cost = $this->repository->store($data);

        return response()->json([
            'message' => 'Custo adicionado com sucesso.',
            'data' => new SeamstressCostResource($cost),
        ], 201);
    }

    public function update(Request $request, SeamstressCost $seamstressCost): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'cost_type' => 'nullable|in:per_piece,fixed',
            'is_active' => 'nullable|in:true,false,1,0',
            'notes' => 'nullable|string|max:255',
        ]);

        $data['is_active'] = filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $updated = $this->repository->update($seamstressCost, $data);

        return response()->json([
            'message' => 'Custo atualizado com sucesso.',
            'data' => new SeamstressCostResource($updated),
        ]);
    }

    public function destroy(SeamstressCost $seamstressCost): JsonResponse
    {
        $this->repository->destroy($seamstressCost);
        return response()->json(['message' => 'Custo removido com sucesso.']);
    }
}
