<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SeamstressDistributionResource;
use App\Models\SeamstressDistribution;
use Illuminate\Http\Request;

class SeamstressDistributionController extends Controller
{
    public function index()
    {
        $distributions = SeamstressDistribution::with([
            'cut',
            'creator',
            'assignments.seamstress',
            'assignments.cutProduction.product',
            'assignments.cutProduction.productVariant',
        ])
        ->orderByDesc('assigned_at')
        ->paginate(20);

        return SeamstressDistributionResource::collection($distributions);
    }

    public function show(SeamstressDistribution $distribution)
    {
        $distribution->load([
            'cut',
            'creator',
            'assignments.seamstress',
            'assignments.cutProduction.product',
            'assignments.cutProduction.productVariant',
        ]);

        return new SeamstressDistributionResource($distribution);
    }

    public function update(Request $request, SeamstressDistribution $distribution)
    {
        $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $distribution->update($request->only('notes'));

        $distribution->load(['cut', 'creator', 'assignments.seamstress', 'assignments.cutProduction.product']);

        return response()->json([
            'message' => 'Distribuição atualizada com sucesso.',
            'data'    => new SeamstressDistributionResource($distribution),
        ]);
    }

    public function destroy(SeamstressDistribution $distribution)
    {
        $distribution->assignments()->delete();
        $distribution->delete();

        return response()->json(['message' => 'Distribuição removida com sucesso.']);
    }
}
