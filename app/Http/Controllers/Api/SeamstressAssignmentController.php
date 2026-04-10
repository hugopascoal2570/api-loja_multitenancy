<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Production\StoreSeamstressAssignmentRequest;
use App\Http\Requests\Api\Production\RecordReturnRequest;
use App\Http\Resources\SeamstressAssignmentResource;
use App\Http\Resources\SeamstressDistributionResource;
use Illuminate\Support\Facades\Auth;
use App\Models\SeamstressAssignment;
use App\Repositories\SeamstressAssignmentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeamstressAssignmentController extends Controller
{
    public function __construct(
        protected SeamstressAssignmentRepository $repository
    ) {}

    public function index()
    {
        $pending = $this->repository->getPendingAssignments();
        return SeamstressAssignmentResource::collection($pending);
    }

    public function store(StoreSeamstressAssignmentRequest $request): JsonResponse
    {
        $assignment = $this->repository->store($request->validated());

        return response()->json([
            'message' => 'Distribuicao registrada com sucesso.',
            'data' => new SeamstressAssignmentResource($assignment->load(['seamstress', 'cutProduction.product'])),
        ], 201);
    }

    public function show(SeamstressAssignment $assignment): SeamstressAssignmentResource
    {
        return new SeamstressAssignmentResource($this->repository->show($assignment->id));
    }

    public function update(Request $request, SeamstressAssignment $assignment): JsonResponse
    {
        $request->validate([
            'quantity_assigned' => ['sometimes', 'integer', 'min:1'],
            'price_per_piece'   => ['sometimes', 'numeric', 'min:0'],
            'notes'             => ['nullable', 'string'],
        ]);

        $updated = $this->repository->update($assignment, $request->only('quantity_assigned', 'price_per_piece', 'notes'));

        return response()->json([
            'message' => 'Distribuição atualizada com sucesso.',
            'data'    => new SeamstressAssignmentResource($updated->load(['seamstress', 'cutProduction.product'])),
        ]);
    }

    public function recordReturn(RecordReturnRequest $request, SeamstressAssignment $assignment): JsonResponse
    {
        $updated = $this->repository->recordReturn(
            $assignment,
            $request->quantity_returned,
            $request->quantity_defective ?? 0
        );

        return response()->json([
            'message' => 'Devolucao registrada com sucesso.',
            'data' => new SeamstressAssignmentResource($updated),
        ]);
    }

    public function storeBatch(Request $request): JsonResponse
    {
        $request->validate([
            'items'                             => ['required', 'array', 'min:1'],
            'items.*.cut_production_id'         => ['required', 'uuid', 'exists:cut_productions,id'],
            'items.*.seamstresses'              => ['required', 'array', 'min:1'],
            'items.*.seamstresses.*.seamstress_id' => ['required', 'uuid', 'exists:seamstresses,id'],
            'items.*.seamstresses.*.quantity'   => ['required', 'integer', 'min:1'],
            'items.*.seamstresses.*.price_per_piece' => ['nullable', 'numeric', 'min:0'],
            'notes'                             => ['nullable', 'string'],
        ]);

        $distribution = $this->repository->storeBatch(
            $request->items,
            $request->notes,
            Auth::id()
        );

        return response()->json([
            'message' => 'Distribuição criada com sucesso.',
            'data'    => new SeamstressDistributionResource($distribution),
        ], 201);
    }

    public function destroy(SeamstressAssignment $assignment): JsonResponse
    {
        $this->repository->destroy($assignment);
        return response()->json(['message' => 'Distribuicao removida com sucesso.']);
    }

    public function bySeamstress(string $seamstressId)
    {
        $assignments = $this->repository->findBySeamstress($seamstressId);
        return SeamstressAssignmentResource::collection($assignments);
    }

    public function byProduction(string $productionId)
    {
        $assignments = $this->repository->findByProduction($productionId);
        return SeamstressAssignmentResource::collection($assignments);
    }
}
