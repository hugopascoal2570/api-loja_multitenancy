<?php
// app/Observers/BannerObserver.php

namespace App\Observers;

use App\Models\Banner;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BannerObserver
{
    public function saving(Banner $banner)
    {
        // Se houver end_date e já passou, marque como inactive
        if ($banner->end_date && Carbon::now()->greaterThan($banner->end_date)) {
            $banner->active = false;
        }
    }
}