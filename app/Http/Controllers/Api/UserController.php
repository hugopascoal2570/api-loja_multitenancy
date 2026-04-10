<?php

namespace App\Http\Controllers\Api;

use App\DTO\Users\CreateUserDTO;
use App\DTO\Users\EditUserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreUserRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function __construct(private UserRepository $userRepository)
    { 
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $users = $this->userRepository->getPaginate(
            totalPerPage: min((int) ($request->total_per_page ?? 15), 100),
            page: $request->page ?? 1,
            filter: $request->get('filter', ''),
        );
        return UserResource::collection($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $user = $this->userRepository->createNew(new CreateUserDTO(... $request->validated()));
        return new UserResource($user);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if (!$user = $this->userRepository->findById($id)) {
            return response()->json(['message' => 'user not found'], Response::HTTP_NOT_FOUND);
        }
        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        $response = $this->userRepository->update(new EditUserDTO(...[$id, ...$request->validated()]));
        if (!$response) {
            return response()->json(['message' => 'user not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['message' => 'user updated with success']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (!$this->userRepository->delete($id)) {
            return response()->json(['message' => 'user not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    public function makeAdmin(string $id): JsonResponse
    {
        $currentAdmin = auth()->user();

        if ($currentAdmin->id === $id) {
            return response()->json(['message' => 'Não é possível alterar o próprio status de admin.'], 403);
        }

        if (!$this->userRepository->setAdminStatus($id, true)) {
            return response()->json(['message' => 'user not found'], Response::HTTP_NOT_FOUND);
        }

        Log::channel('audit')->info('Admin status concedido', [
            'target_user_id'   => $id,
            'granted_by'       => $currentAdmin->id,
            'granted_by_email' => $currentAdmin->email,
            'ip'               => request()->ip(),
        ]);

        return response()->json(['message' => 'user promoted to admin']);
    }

    public function revokeAdmin(string $id): JsonResponse
    {
        $currentAdmin = auth()->user();

        if ($currentAdmin->id === $id) {
            return response()->json(['message' => 'Não é possível alterar o próprio status de admin.'], 403);
        }

        if (!$this->userRepository->setAdminStatus($id, false)) {
            return response()->json(['message' => 'user not found'], Response::HTTP_NOT_FOUND);
        }

        Log::channel('audit')->info('Admin status revogado', [
            'target_user_id'   => $id,
            'revoked_by'       => $currentAdmin->id,
            'revoked_by_email' => $currentAdmin->email,
            'ip'               => request()->ip(),
        ]);

        return response()->json(['message' => 'admin privileges removed from user']);
    }

public function search(Request $request)
{
    $data = $request->validate([
        'q' => 'required|string|min:2',
        'total_per_page' => 'nullable|integer|min:1|max:100',
        'page' => 'nullable|integer|min:1',
    ]);

    $term = $data['q'];
    $perPage = $data['total_per_page'] ?? 15;
    $page = $data['page'] ?? 1;

    $users = $this->userRepository->searchByNameOrEmail(
        term: $term,
        totalPerPage: $perPage,
        page: $page,
    );

    return UserResource::collection($users);
}

}