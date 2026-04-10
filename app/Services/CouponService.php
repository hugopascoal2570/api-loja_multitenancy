<?php

namespace App\Services;

use App\Models\Coupon;
use App\Repositories\CouponRepository;

class CouponService
{
    public function __construct(
        private CouponRepository $repository
    ) {}

    public function validate(string $code, string $userId): array
    {
        $coupon = $this->repository->findByCode($code);

        if (!$coupon) {
            return [
                'valid' => false,
                'message' => 'Cupom não encontrado.',
            ];
        }

        if (!$coupon->is_active) {
            return [
                'valid' => false,
                'message' => 'Cupom inativo.',
            ];
        }

        $now = now()->startOfDay();

        if ($coupon->valid_from && $now->lt($coupon->valid_from)) {
            return [
                'valid' => false,
                'message' => 'Este cupom ainda não está válido.',
            ];
        }

        if ($coupon->valid_until && $now->gt($coupon->valid_until)) {
            return [
                'valid' => false,
                'message' => 'Este cupom expirou.',
            ];
        }

        if ($coupon->max_uses && $coupon->current_uses >= $coupon->max_uses) {
            return [
                'valid' => false,
                'message' => 'Este cupom atingiu o limite de uso.',
            ];
        }

        $userUsageCount = $this->repository->getUserUsageCount($coupon->id, $userId);

        if ($userUsageCount >= $coupon->max_uses_per_user) {
            return [
                'valid' => false,
                'message' => 'Você já utilizou este cupom o máximo de vezes permitido.',
            ];
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'message' => 'Cupom válido!',
        ];
    }

    public function calculateDiscount(Coupon $coupon, float $subtotal): float
    {
        if ($coupon->type === 'fixed') {
            return min($coupon->value, $subtotal);
        }

        if ($coupon->type === 'percentage') {
            return ($subtotal * $coupon->value) / 100;
        }

        return 0;
    }

    public function apply(Coupon $coupon, string $userId, ?string $orderId = null): void
    {
        $this->repository->recordUsage($coupon, $userId, $orderId);
    }
}
