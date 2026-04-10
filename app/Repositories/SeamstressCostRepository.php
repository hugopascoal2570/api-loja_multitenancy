<?php

namespace App\Repositories;

use App\Models\SeamstressCost;

class SeamstressCostRepository
{
    public function __construct(protected SeamstressCost $model) {}

    public function findBySeamstress(string $seamstressId)
    {
        return $this->model
            ->where('seamstress_id', $seamstressId)
            ->orderBy('name')
            ->get();
    }

    public function store(array $data): SeamstressCost
    {
        return $this->model->create($data);
    }

    public function update(SeamstressCost $cost, array $data): SeamstressCost
    {
        $cost->update($data);
        return $cost->fresh();
    }

    public function destroy(SeamstressCost $cost): void
    {
        $cost->delete();
    }
}
