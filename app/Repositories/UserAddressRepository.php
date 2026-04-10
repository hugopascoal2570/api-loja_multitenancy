<?php

namespace App\Repositories;

use App\DTO\UserAddress\UserAddressDTO;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Collection;

class UserAddressRepository
{
    public function __construct(protected UserAddress $model)
    {
    }

    public function getAllByUser(string $userId): Collection
    {
        return $this->model
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();
    }

    public function findById(string $id): ?UserAddress
    {
        return $this->model->find($id);
    }

    public function findByIdAndUser(string $id, string $userId): ?UserAddress
    {
        return $this->model->where('id', $id)->where('user_id', $userId)->first();
    }

    public function create(UserAddressDTO $dto): UserAddress
    {
        if ($dto->is_default) {
            $this->clearDefault($dto->user_id);
        }

        $address = $this->model->create((array) $dto);

        // Se for o primeiro endereço, marca como padrão automaticamente
        $count = $this->model->where('user_id', $dto->user_id)->count();
        if ($count === 1) {
            $address->update(['is_default' => true]);
        }

        return $address;
    }

    public function update(UserAddress $address, UserAddressDTO $dto): UserAddress
    {
        if ($dto->is_default) {
            $this->clearDefault($address->user_id);
        }

        $data = collect((array) $dto)->except(['id', 'user_id'])->toArray();
        $address->update($data);

        return $address->fresh();
    }

    public function delete(UserAddress $address): bool
    {
        $wasDefault = $address->is_default;
        $userId = $address->user_id;

        $deleted = $address->delete();

        // Se deletou o endereço padrão, promove o primeiro restante
        if ($deleted && $wasDefault) {
            $first = $this->model->where('user_id', $userId)->first();
            if ($first) {
                $first->update(['is_default' => true]);
            }
        }

        return $deleted;
    }

    public function setDefault(UserAddress $address): UserAddress
    {
        $this->clearDefault($address->user_id);
        $address->update(['is_default' => true]);

        return $address->fresh();
    }

    protected function clearDefault(string $userId): void
    {
        $this->model
            ->where('user_id', $userId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
