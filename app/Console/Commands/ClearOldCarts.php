<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cart;

class ClearOldCarts extends Command
{
    protected $signature = 'carts:clear-old';
    protected $description = 'Clear carts older than 7 days and not completed';

    public function handle()
    {
        // Cart::where('status', 'pending')
        //     ->where('updated_at', '<', now()->subDays(7))
        //     ->delete();

        // $this->info('Old pending carts cleared.');
    }
}
