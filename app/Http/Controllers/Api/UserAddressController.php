<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UserAddress\StoreUserAddressRequest;
use App\Http\Requests\Api\UserAddress\UpdateUserAddressRequest;
use App\Http\Resources\UserAddressResource;
use App\DTO\UserAddress\UserAddressDTO;
use App\Repositories\UserAddressRepository;
use Illuminate\Support\Facades\Auth;

class UserAddressController extends Controller
{
    public function __construct(private UserAddressRepository $repository)
    {
    }

    public function index()
    {
        $addresses = $this->repository->getAllByUser(Auth::id());

        return UserAddressResource::collection($addresses);
    }

    public function store(StoreUserAddressRequest $request)
    {
        $dto = new UserAddressDTO(
            user_id: Auth::id(),
            label: $request->get('label'),
            recipient_name: $request->get('recipient_name'),
            address: $request->get('address'),
            number: $request->get('number'),
            neighborhood: $request->get('neighborhood'),
            complement: $request->get('complement'),
            city: $request->get('city'),
            state: $request->get('state'),
            zip_code: $request->get('zip_code'),
            phone: $request->get('phone'),
            is_default: (bool) $request->get('is_default', false),
        );

        $address = $this->repository->create($dto);

        return (new UserAddressResource($address))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id)
    {
        $address = $this->repository->findByIdAndUser($id, Auth::id());

        if (!$address) {
            return response()->json(['message' => 'Endereço não encontrado.'], 404);
        }

        return new UserAddressResource($address);
    }

    public function update(UpdateUserAddressRequest $request, string $id)
    {
        $address = $this->repository->findByIdAndUser($id, Auth::id());

        if (!$address) {
            return response()->json(['message' => 'Endereço não encontrado.'], 404);
        }

        $dto = new UserAddressDTO(
            id: $address->id,
            user_id: $address->user_id,
            label: $request->get('label'),
            recipient_name: $request->get('recipient_name'),
            address: $request->get('address'),
            number: $request->get('number'),
            neighborhood: $request->get('neighborhood'),
            complement: $request->get('complement'),
            city: $request->get('city'),
            state: $request->get('state'),
            zip_code: $request->get('zip_code'),
            phone: $request->get('phone'),
            is_default: (bool) $request->get('is_default', $address->is_default),
        );

        $updated = $this->repository->update($address, $dto);

        return new UserAddressResource($updated);
    }

    public function destroy(string $id)
    {
        $address = $this->repository->findByIdAndUser($id, Auth::id());

        if (!$address) {
            return response()->json(['message' => 'Endereço não encontrado.'], 404);
        }

        $this->repository->delete($address);

        return response()->json(['message' => 'Endereço removido com sucesso.']);
    }

    public function setDefault(string $id)
    {
        $address = $this->repository->findByIdAndUser($id, Auth::id());

        if (!$address) {
            return response()->json(['message' => 'Endereço não encontrado.'], 404);
        }

        $updated = $this->repository->setDefault($address);

        return new UserAddressResource($updated);
    }
}
