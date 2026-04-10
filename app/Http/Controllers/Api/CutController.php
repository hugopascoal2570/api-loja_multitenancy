<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Production\StoreCutRequest;
use App\Http\Requests\Api\Production\UpdateCutRequest;
use App\Http\Resources\CutResource;
use App\DTO\Production\CutDTO;
use App\Models\Cut;
use App\Repositories\CutRepository;
use Illuminate\Http\JsonResponse;

class CutController extends Controller
{
    public function __construct(
        protected CutRepository $repository
    ) {}

    public function index()
    {
        $cuts = $this->repository->paginate();
        return CutResource::collection($cuts);
    }

    public function store(StoreCutRequest $request): JsonResponse
    {
        $dto = CutDTO::fromRequest($request);
        $cut = $this->repository->store($dto);

        return response()->json([
            'message' => 'Corte criado com sucesso.',
            'data' => new CutResource($cut),
        ], 201);
    }

    public function show(string $id): CutResource
    {
        return new CutResource($this->repository->show($id));
    }

    public function update(UpdateCutRequest $request, Cut $cut): JsonResponse
    {
        $dto = CutDTO::fromRequest($request);
        $updated = $this->repository->update($cut, $dto);

        return response()->json([
            'message' => 'Corte atualizado com sucesso.',
            'data' => new CutResource($updated),
        ]);
    }

    public function destroy(Cut $cut): JsonResponse
    {
        $this->repository->destroy($cut);
        return response()->json(['message' => 'Corte deletado com sucesso.']);
    }

    public function updateStatus(Cut $cut, string $status): JsonResponse
    {
        if (!in_array($status, ['pending', 'in_progress', 'completed'])) {
            return response()->json(['message' => 'Status invalido.'], 422);
        }

        $updated = $this->repository->updateStatus($cut, $status);

        return response()->json([
            'message' => 'Status do corte atualizado.',
            'data' => new CutResource($updated),
        ]);
    }

    public function summary(): JsonResponse
    {
        return response()->json($this->repository->getSummary());
    }
}
