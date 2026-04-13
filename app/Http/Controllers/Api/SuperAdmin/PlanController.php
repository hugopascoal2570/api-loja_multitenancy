<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    /**
     * GET /api/superadmin/plans
     */
    public function index(): JsonResponse
    {
        $plans = Plan::withCount('tenants')->orderBy('name')->get();

        return response()->json([
            'plans'            => $plans,
            'available_features' => Plan::ALL_FEATURES,
        ]);
    }

    /**
     * POST /api/superadmin/plans
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:191',
            'slug'        => 'nullable|string|max:100|alpha_dash|unique:plans,slug',
            'description' => 'nullable|string',
            'price'       => 'nullable|numeric|min:0',
            'is_active'   => 'boolean',
            'features'    => 'required|array',
            'features.*'  => ['string', Rule::in(Plan::ALL_FEATURES)],
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        // Garante unicidade do slug caso omitido
        $baseSlug = $data['slug'];
        $count = 1;
        while (Plan::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $baseSlug . '-' . $count++;
        }

        $plan = Plan::create($data);

        return response()->json($plan, 201);
    }

    /**
     * GET /api/superadmin/plans/{plan}
     */
    public function show(Plan $plan): JsonResponse
    {
        $plan->loadCount('tenants');
        return response()->json([
            'plan'               => $plan,
            'available_features' => Plan::ALL_FEATURES,
        ]);
    }

    /**
     * PUT /api/superadmin/plans/{plan}
     */
    public function update(Request $request, Plan $plan): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:191',
            'slug'        => ['sometimes', 'string', 'max:100', 'alpha_dash', Rule::unique('plans', 'slug')->ignore($plan->id)],
            'description' => 'nullable|string',
            'price'       => 'nullable|numeric|min:0',
            'is_active'   => 'sometimes|boolean',
            'features'    => 'sometimes|array',
            'features.*'  => ['string', Rule::in(Plan::ALL_FEATURES)],
        ]);

        $plan->update($data);

        return response()->json($plan);
    }

    /**
     * DELETE /api/superadmin/plans/{plan}
     */
    public function destroy(Plan $plan): JsonResponse
    {
        if ($plan->tenants()->exists()) {
            return response()->json([
                'message' => 'Não é possível excluir este plano pois há tenants associados a ele.',
            ], 422);
        }

        $plan->delete();
        return response()->json(null, 204);
    }

    /**
     * GET /api/superadmin/plans/features
     * Lista todas as feature keys disponíveis na plataforma.
     */
    public function features(): JsonResponse
    {
        return response()->json(['features' => Plan::ALL_FEATURES]);
    }
}
