<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Production\StoreSeamstressRequest;
use App\Http\Resources\SeamstressResource;
use App\DTO\Production\SeamstressDTO;
use App\Models\Seamstress;
use App\Repositories\SeamstressRepository;
use Illuminate\Http\JsonResponse;

class SeamstressController extends Controller
{
    public function __construct(
        protected SeamstressRepository $repository
    ) {}

    public function index()
    {
        $seamstresses = $this->repository->paginate();
        return SeamstressResource::collection($seamstresses);
    }

    public function all()
    {
        return SeamstressResource::collection($this->repository->all());
    }

    public function store(StoreSeamstressRequest $request): JsonResponse
    {
        $dto = SeamstressDTO::fromRequest($request);
        $seamstress = $this->repository->store($dto);

        return response()->json([
            'message' => 'Costureira cadastrada com sucesso.',
            'data' => new SeamstressResource($seamstress),
        ], 201);
    }

    public function show(string $id): SeamstressResource
    {
        return new SeamstressResource($this->repository->show($id));
    }

    public function update(StoreSeamstressRequest $request, Seamstress $seamstress): JsonResponse
    {
        $dto = SeamstressDTO::fromRequest($request);
        $updated = $this->repository->update($seamstress, $dto);

        return response()->json([
            'message' => 'Costureira atualizada com sucesso.',
            'data' => new SeamstressResource($updated),
        ]);
    }

    public function destroy(Seamstress $seamstress): JsonResponse
    {
        $this->repository->destroy($seamstress);
        return response()->json(['message' => 'Costureira deletada com sucesso.']);
    }

    public function performance(string $id): JsonResponse
    {
        $stats = $this->repository->getPerformanceStats($id);
        return response()->json($stats);
    }
}
