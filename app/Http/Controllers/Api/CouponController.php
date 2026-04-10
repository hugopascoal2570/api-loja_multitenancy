<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coupon\StoreCouponRequest;
use App\Http\Requests\Api\Coupon\UpdateCouponRequest;
use App\Http\Requests\Api\Coupon\ValidateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Repositories\CouponRepository;
use App\Services\CouponService;

class CouponController extends Controller
{
    public function __construct(
        private CouponRepository $repository,
        private CouponService $service
    ) {}

    /**
     * Lista todos os cupons (admin)
     */
    public function index()
    {
        $coupons = $this->repository->getAll();
        return CouponResource::collection($coupons);
    }

    /**
     * Exibe detalhes de um cupom (admin)
     */
    public function show(string $id)
    {
        $coupon = $this->repository->find($id);
        return new CouponResource($coupon);
    }

    /**
     * Cria um novo cupom (admin)
     */
    public function store(StoreCouponRequest $request)
    {
        $coupon = $this->repository->create($request->validated());

        return response()->json([
            'message' => 'Cupom criado com sucesso.',
            'coupon' => new CouponResource($coupon),
        ], 201);
    }

    /**
     * Atualiza um cupom (admin)
     */
    public function update(UpdateCouponRequest $request, string $id)
    {
        $coupon = $this->repository->find($id);
        $updated = $this->repository->update($coupon, $request->validated());

        return response()->json([
            'message' => 'Cupom atualizado com sucesso.',
            'coupon' => new CouponResource($updated),
        ]);
    }

    /**
     * Remove um cupom (admin)
     */
    public function destroy(string $id)
    {
        $coupon = $this->repository->find($id);
        $this->repository->delete($coupon);

        return response()->json([
            'message' => 'Cupom removido com sucesso.',
        ]);
    }

    /**
     * Valida um cupom (usuário autenticado)
     */
    public function validate(ValidateCouponRequest $request)
    {
        $user = auth()->user();
        $code = $request->validated()['code'];

        $result = $this->service->validate($code, $user->id);

        if (!$result['valid']) {
            return response()->json([
                'valid' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'message' => $result['message'],
            'coupon' => new CouponResource($result['coupon']),
        ]);
    }
}
