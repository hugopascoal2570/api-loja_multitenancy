<?php

namespace App\Repositories;

use App\DTO\Production\SeamstressDTO;
use App\Models\Seamstress;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SeamstressRepository
{
    public function __construct(protected Seamstress $model) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with('activeCosts');

        if (request()->has('active')) {
            $query->where('is_active', filter_var(request('active'), FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function all()
    {
        return $this->model
            ->where('is_active', true)
            ->with('activeCosts')
            ->orderBy('name')
            ->get();
    }

    public function store(SeamstressDTO $data): Seamstress
    {
        return DB::transaction(function () use ($data) {
            $seamstress = $this->model->create([
                'name' => $data->name,
                'phone' => $data->phone,
                'address' => $data->address,
                'price_per_piece' => $data->price_per_piece,
                'is_active' => $data->is_active,
                'notes' => $data->notes,
            ]);

            if (!empty($data->costs)) {
                $seamstress->costs()->createMany($data->costs);
            }

            return $seamstress->load('costs');
        });
    }

    public function show(string $id): Seamstress
    {
        return $this->model
            ->with([
                'costs',
                'assignments.cutProduction.product',
                'assignments.cutProduction.cut',
            ])
            ->findOrFail($id);
    }

    public function update(Seamstress $seamstress, SeamstressDTO $data): Seamstress
    {
        return DB::transaction(function () use ($seamstress, $data) {
            $seamstress->update([
                'name' => $data->name,
                'phone' => $data->phone,
                'address' => $data->address,
                'price_per_piece' => $data->price_per_piece,
                'is_active' => $data->is_active,
                'notes' => $data->notes,
            ]);

            return $seamstress->load('costs');
        });
    }

    public function destroy(Seamstress $seamstress): void
    {
        $seamstress->delete();
    }

    public function getPerformanceStats(string $id): array
    {
        $seamstress = $this->show($id);

        return [
            'total_pieces_assigned' => $seamstress->assignments->sum('quantity_assigned'),
            'total_pieces_returned' => $seamstress->assignments->sum('quantity_returned'),
            'total_defective' => $seamstress->assignments->sum('quantity_defective'),
            'defect_rate' => $seamstress->defect_rate,
            'total_earned' => $seamstress->assignments->sum(fn($a) => $a->total_sewing_cost),
            'pending_assignments' => $seamstress->assignments->where('status', '!=', 'returned')->count(),
        ];
    }
}
