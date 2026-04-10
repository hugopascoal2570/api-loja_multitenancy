<?php

namespace App\Repositories;

use App\Models\CutProduction;
use App\Models\Seamstress;
use App\Models\SeamstressAssignment;
use App\Models\SeamstressDistribution;
use Illuminate\Support\Facades\DB;

class SeamstressAssignmentRepository
{
    public function __construct(protected SeamstressAssignment $model) {}

    public function findBySeamstress(string $seamstressId)
    {
        return $this->model
            ->where('seamstress_id', $seamstressId)
            ->with(['cutProduction.product', 'cutProduction.cut'])
            ->orderByDesc('assigned_at')
            ->get();
    }

    public function findByProduction(string $productionId)
    {
        return $this->model
            ->where('cut_production_id', $productionId)
            ->with('seamstress')
            ->get();
    }

    public function show(string $id): SeamstressAssignment
    {
        return $this->model
            ->with([
                'seamstress.costs',
                'cutProduction.fabricRoll',
                'cutProduction.cut',
                'cutProduction.product',
            ])
            ->findOrFail($id);
    }

    public function store(array $data): SeamstressAssignment
    {
        return DB::transaction(function () use ($data) {
            // Get seamstress default price if not provided
            if (!isset($data['price_per_piece'])) {
                $seamstress = Seamstress::findOrFail($data['seamstress_id']);
                $data['price_per_piece'] = $seamstress->price_per_piece;
            }

            $data['assigned_at'] = $data['assigned_at'] ?? now();

            return $this->model->create($data);
        });
    }

    public function update(SeamstressAssignment $assignment, array $data): SeamstressAssignment
    {
        $assignment->update($data);
        return $assignment->fresh();
    }

    public function recordReturn(SeamstressAssignment $assignment, int $returned, int $defective = 0): SeamstressAssignment
    {
        return DB::transaction(function () use ($assignment, $returned, $defective) {
            $newDefective = $assignment->quantity_defective + $defective;

            // Peças com defeito são fisicamente devolvidas — garantir que returned >= defective
            $returned    = max($returned, $defective);
            $newReturned = $assignment->quantity_returned + $returned;

            $status = $newReturned >= $assignment->quantity_assigned ? 'returned' : 'in_progress';

            $assignment->update([
                'quantity_returned' => $newReturned,
                'quantity_defective' => $newDefective,
                'status' => $status,
                'returned_at' => $status === 'returned' ? now() : null,
            ]);

            return $assignment->fresh(['seamstress', 'cutProduction']);
        });
    }

    public function destroy(SeamstressAssignment $assignment): void
    {
        $assignment->delete();
    }

    public function storeBatch(array $items, ?string $notes = null, ?string $userId = null): SeamstressDistribution
    {
        return DB::transaction(function () use ($items, $notes, $userId) {
            // Pega o cut_id da primeira produção
            $firstProduction = CutProduction::findOrFail($items[0]['cut_production_id']);

            $distribution = SeamstressDistribution::create([
                'cut_id'      => $firstProduction->cut_id,
                'created_by'  => $userId,
                'notes'       => $notes,
                'assigned_at' => now(),
            ]);

            foreach ($items as $item) {
                $cutProductionId = $item['cut_production_id'];

                foreach ($item['seamstresses'] as $seamstressData) {
                    if (empty($seamstressData['quantity']) || $seamstressData['quantity'] <= 0) {
                        continue;
                    }

                    $seamstress = Seamstress::findOrFail($seamstressData['seamstress_id']);

                    $this->model->create([
                        'distribution_id'   => $distribution->id,
                        'seamstress_id'     => $seamstress->id,
                        'cut_production_id' => $cutProductionId,
                        'quantity_assigned' => $seamstressData['quantity'],
                        'price_per_piece'   => $seamstressData['price_per_piece'] ?? $seamstress->price_per_piece,
                        'assigned_at'       => now(),
                    ]);
                }
            }

            return $distribution->load(['cut', 'creator', 'assignments.seamstress', 'assignments.cutProduction.product']);
        });
    }

    public function getPendingAssignments()
    {
        return $this->model
            ->whereIn('status', ['assigned', 'in_progress'])
            ->with(['seamstress', 'cutProduction.product'])
            ->orderBy('assigned_at')
            ->get();
    }
}
