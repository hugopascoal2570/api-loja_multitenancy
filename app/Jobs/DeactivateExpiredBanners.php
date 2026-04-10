<?php

namespace App\Jobs;

use App\Models\Banner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeactivateExpiredBanners implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue,SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        Banner::whereNotNull('end_date')
              ->where('end_date', '<', now())
              ->where('active', true)
              ->update(['active' => false]);
    }
}
