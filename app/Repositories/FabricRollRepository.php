<?php

namespace App\Repositories;

use App\Models\FabricRoll;

class FabricRollRepository
{
    public function __construct(protected FabricRoll $model) {}

    public function findByCut(string $cutId)
    {
        return $this->model
            ->where('cut_id', $cutId)
            ->with('productions')
            ->get();
    }

    public function show(string $id): FabricRoll
    {
        return $this->model
            ->with(['cut', 'productions.product'])
            ->findOrFail($id);
    }

    public function store(array $data): FabricRoll
    {
        return $this->model->create($data);
    }

    public function update(FabricRoll $roll, array $data): FabricRoll
    {
        $roll->update($data);
        return $roll->fresh();
    }

    public function destroy(FabricRoll $roll): void
    {
        $roll->delete();
    }
}
