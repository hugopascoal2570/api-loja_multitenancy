<?php

use App\Console\Commands\ClearOldCarts;
use App\Console\Commands\SyncPaymentStatuses;
use App\Console\Commands\SyncPendingOrders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use App\Jobs\DeactivateExpiredBanners;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new DeactivateExpiredBanners)->hourly();
Schedule::command('coupons:deactivate-expired')->daily();
Schedule::job(new SyncPendingOrders)->everyMinute();
Schedule::command('carts:complete-paid')->everyMinute();
#Schedule::job(new ClearOldCarts)->weekly();
Schedule::command('newsletter:send-scheduled')->everyMinute();
Schedule::command('db:backup --compress --keep=2')->dailyAt('03:00');
Schedule::command('shipping:sync-tracking')->everyThirtyMinutes();