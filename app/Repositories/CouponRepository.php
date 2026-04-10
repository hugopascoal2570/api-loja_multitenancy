<?php

namespace App\Repositories;

use App\Models\Coupon;
use App\Models\CouponUsage;

class CouponRepository
{
    public function findByCode(string $code): ?Coupon
    {
        return Coupon::where('code', strtoupper($code))->first();
    }

    public function getAll()
    {
        return Coupon::with('usages')
            ->withCount('usages')
            ->latest()
            ->get();
    }

    public function find(string $id): Coupon
    {
        return Coupon::findOrFail($id);
    }

    public function create(array $data): Coupon
    {
        $data['code'] = strtoupper($data['code']);
        return Coupon::create($data);
    }

    public function update(Coupon $coupon, array $data): Coupon
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }
        $coupon->update($data);
        return $coupon->fresh();
    }

    public function delete(Coupon $coupon): void
    {
        $coupon->delete();
    }

    public function recordUsage(Coupon $coupon, string $userId, ?string $orderId = null): CouponUsage
    {
        $coupon->increment('current_uses');

        return CouponUsage::create([
            'coupon_id' => $coupon->id,
            'user_id' => $userId,
            'order_id' => $orderId,
        ]);
    }

    public function getUserUsageCount(string $couponId, string $userId): int
    {
        return CouponUsage::where('coupon_id', $couponId)
            ->where('user_id', $userId)
            ->count();
    }
}
