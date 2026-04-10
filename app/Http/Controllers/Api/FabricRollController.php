<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Production\StoreFabricRollRequest;
use App\Http\Resources\FabricRollResource;
use App\Models\Cut;
use App\Models\FabricRoll;
use App\Repositories\FabricRollRepository;
use Illuminate\Http\JsonResponse;

class FabricRollController extends Controller
{
    public function __construct(
        protected FabricRollRepository $repository
    ) {}

    public function index(Cut $cut)
    {
        $rolls = $this->repository->findByCut($cut->id);
        return FabricRollResource::collection($rolls);
    }

    public function store(StoreFabricRollRequest $request, Cut $cut): JsonResponse
    {
        $data = $request->validated();
        $data['cut_id'] = $cut->id;

        $roll = $this->repository->store($data);

        return response()->json([
            'message' => 'Rolo de tecido adicionado com sucesso.',
            'data' => new FabricRollResource($roll),
        ], 201);
    }

    public function show(FabricRoll $fabricRoll): FabricRollResource
    {
        return new FabricRollResource($this->repository->show($fabricRoll->id));
    }

    public function update(StoreFabricRollRequest $request, FabricRoll $fabricRoll): JsonResponse
    {
        $updated = $this->repository->update($fabricRoll, $request->validated());

        return response()->json([
            'message' => 'Rolo de tecido atualizado com sucesso.',
            'data' => new FabricRollResource($updated),
        ]);
    }

    public function destroy(FabricRoll $fabricRoll): JsonResponse
    {
        $this->repository->destroy($fabricRoll);
        return response()->json(['message' => 'Rolo de tecido deletado com sucesso.']);
    }
}
