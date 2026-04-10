<?php

namespace App\Observers;

use App\Models\Coupon;
use Carbon\Carbon;

class CouponObserver
{
    /**
     * Handle the Coupon "saving" event.
     * Verifica se o cupom expirou e inativa automaticamente.
     */
    public function saving(Coupon $coupon): void
    {
        // Se houver valid_until e já passou, marque como inativo
        if ($coupon->valid_until && Carbon::now()->startOfDay()->greaterThan($coupon->valid_until)) {
            $coupon->is_active = false;
        }
    }

    /**
     * Handle the Coupon "retrieved" event.
     * Verifica se o cupom expirou ao ser recuperado do banco.
     */
    public function retrieved(Coupon $coupon): void
    {
        // Se houver valid_until e já passou, atualiza para inativo
        if ($coupon->is_active && $coupon->valid_until && Carbon::now()->startOfDay()->greaterThan($coupon->valid_until)) {
            $coupon->is_active = false;
            $coupon->saveQuietly(); // Salva sem disparar eventos para evitar loop
        }
    }
}
