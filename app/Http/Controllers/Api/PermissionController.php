<?php

namespace App\Http\Controllers\Api;

use App\DTO\Permissions\CreatePermissionDTO;
use App\DTO\Permissions\EditPermissionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePermissionRequest;
use App\Http\Requests\Api\UpdatePermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Repositories\PermissionRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PermissionController extends Controller
{
    public function __construct(private PermissionRepository $permissionRepository)
    { 
    }

    /**
     * Display a listing of the resource.
     */
    public function groups(): \Illuminate\Http\JsonResponse
    {
        $permissions = \App\Models\Permission::orderBy('name')->get();

        $grouped = $permissions
            ->groupBy(fn($p) => (new PermissionResource($p))->resolve()['group'])
            ->map(fn($items, $group) => [
                'group' => $group,
                'total' => $items->count(),
                'permissions' => PermissionResource::collection($items)->resolve(),
            ])
            ->sortBy('group')
            ->values();

        return response()->json($grouped);
    }

    public function index(Request $request)
    {
        $permissions = $this->permissionRepository->getPaginate(
            totalPerPage: $request->integer('per_page', 20),
            page: $request->integer('page', 1),
            filter: $request->get('filter', ''),
        );

        return response()->json([
            'data' => PermissionResource::collection($permissions),
            'pagination' => [
                'current_page' => $permissions->currentPage(),
                'last_page'    => $permissions->lastPage(),
                'per_page'     => $permissions->perPage(),
                'total'        => $permissions->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePermissionRequest $request)
    {
        $permission = $this->permissionRepository->createNew(new CreatePermissionDTO(... $request->validated()));
        return new PermissionResource($permission);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if (!$permission = $this->permissionRepository->findById($id)) {
            return response()->json(['message' => 'permission not found'], Response::HTTP_NOT_FOUND);
        }
        return new PermissionResource($permission);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePermissionRequest $request, string $id)
    {
        $response = $this->permissionRepository->update(new EditPermissionDTO(...[$id, ...$request->validated()]));
        if (!$response) {
            return response()->json(['message' => 'permission not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['message' => 'permission updated with success']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (!$this->permissionRepository->delete($id)) {
            return response()->json(['message' => 'permission not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->json([], Response::HTTP_NO_CONTENT);
    }
}