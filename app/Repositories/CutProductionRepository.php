<?php

namespace App\Repositories;

use App\Models\CutProduction;
use Illuminate\Support\Facades\DB;

class CutProductionRepository
{
    public function __construct(protected CutProduction $model) {}

    public function findByCut(string $cutId)
    {
        return $this->model
            ->where('cut_id', $cutId)
            ->with([
                'cut',
                'fabricRoll',
                'product',
                'productVariant',
                'assignments.seamstress.activeCosts',
            ])
            ->get();
    }

    public function show(string $id): CutProduction
    {
        return $this->model
            ->with([
                'cut',
                'fabricRoll',
                'product',
                'productVariant',
                'assignments.seamstress.costs',
            ])
            ->findOrFail($id);
    }

    public function store(array $data): CutProduction
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
        });
    }

    public function storeBatch(array $items): array
    {
        return DB::transaction(function () use ($items) {
            $productions = [];
            foreach ($items as $data) {
                $productions[] = $this->model->create($data);
            }
            return $productions;
        });
    }

    public function update(CutProduction $production, array $data): CutProduction
    {
        $production->update($data);
        return $production->fresh(['fabricRoll', 'product', 'productVariant']);
    }

    public function linkProduct(CutProduction $production, string $productId, ?string $variantId = null): CutProduction
    {
        $production->update([
            'product_id' => $productId,
            'product_variant_id' => $variantId,
        ]);
        return $production->fresh(['product', 'productVariant']);
    }

    public function destroy(CutProduction $production): void
    {
        $production->delete();
    }
}
