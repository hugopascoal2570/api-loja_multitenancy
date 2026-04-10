<?php

namespace App\Providers;
use App\Models\Banner;
use App\Models\Coupon;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Observers\BannerObserver;
use App\Observers\CouponObserver;
use App\Observers\InventoryMovementObserver;
use App\Observers\OrderObserver;
use App\Observers\ProductVariantObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Arr;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Banner::observe(BannerObserver::class);
        Coupon::observe(CouponObserver::class);
        Order::observe(OrderObserver::class);
        InventoryMovement::observe(InventoryMovementObserver::class);
        ProductVariant::observe(ProductVariantObserver::class);

        if (!Arr::hasMacro('isAssoc')) {
            Arr::macro('isAssoc', function (array $array): bool {
                return array_keys($array) !== range(0, count($array) - 1);
            });
        }
    }
}
