<?php

namespace App\Repositories;

use App\DTO\Production\CutDTO;
use App\Models\Cut;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CutRepository
{
    public function __construct(protected Cut $model) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with([
            'fabricRolls',
            'productions.product',
            'productions.productVariant',
        ]);

        if (request()->has('status')) {
            $query->where('status', request('status'));
        }

        return $query->orderByDesc('cut_number')->paginate($perPage);
    }

    public function store(CutDTO $data): Cut
    {
        return DB::transaction(function () use ($data) {
            $cut = $this->model->create([
                'cutting_labor_cost' => $data->cutting_labor_cost ?? 0,
                'status' => $data->status,
                'notes' => $data->notes,
            ]);

            if (!empty($data->fabricRolls)) {
                $cut->fabricRolls()->createMany($data->fabricRolls);
            }

            return $cut->load(['fabricRolls', 'productions']);
        });
    }

    public function show(string $id): Cut
    {
        return $this->model
            ->with([
                'fabricRolls.productions.product',
                'productions.fabricRoll',
                'productions.product',
                'productions.productVariant',
                'productions.assignments.seamstress',
            ])
            ->findOrFail($id);
    }

    public function update(Cut $cut, CutDTO $data): Cut
    {
        return DB::transaction(function () use ($cut, $data) {
            $cut->update([
                'cutting_labor_cost' => $data->cutting_labor_cost ?? $cut->cutting_labor_cost,
                'status' => $data->status,
                'notes' => $data->notes,
            ]);

            return $cut->load(['fabricRolls', 'productions']);
        });
    }

    public function updateStatus(Cut $cut, string $status): Cut
    {
        $cut->update(['status' => $status]);
        return $cut->fresh();
    }

    public function destroy(Cut $cut): void
    {
        $cut->delete();
    }

    public function addFabricRoll(Cut $cut, array $data): Cut
    {
        $cut->fabricRolls()->create($data);
        return $cut->load('fabricRolls');
    }

    public function getSummary(): array
    {
        // Usar uma única query com selectRaw para contar status
        $statusCounts = $this->model
            ->selectRaw("
                COUNT(*) as total_cuts,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_cuts,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_cuts,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_cuts,
                SUM(cutting_labor_cost) as total_cutting_cost
            ")
            ->first();

        // Calcular total_fabric_cost diretamente no banco
        $totalFabricCost = DB::table('fabric_rolls')
            ->whereNull('deleted_at')
            ->selectRaw("
                SUM(
                    CASE
                        WHEN price_per_roll > 0 THEN price_per_roll
                        ELSE COALESCE(price_per_meter, 0) * COALESCE(meters, 0)
                    END
                ) as total
            ")
            ->value('total') ?? 0;

        return [
            'total_cuts' => (int) $statusCounts->total_cuts,
            'pending_cuts' => (int) $statusCounts->pending_cuts,
            'in_progress_cuts' => (int) $statusCounts->in_progress_cuts,
            'completed_cuts' => (int) $statusCounts->completed_cuts,
            'total_fabric_cost' => (float) $totalFabricCost,
            'total_cutting_cost' => (float) $statusCounts->total_cutting_cost,
        ];
    }
}
