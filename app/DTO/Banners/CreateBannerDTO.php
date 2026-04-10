<?php

namespace App\DTO\Banners;

use DateTime;

class CreateBannerDTO
{
    public function __construct(
        public string    $name,
        public ?string   $description = null,
        public ?string   $link = null,
        public bool      $is_featured = false,
        public int       $position = 1,
        public ?DateTime $start_date = null,
        public ?DateTime $end_date = null,
        public string    $image_url = '',
        public string    $device_type = 'all'
    ) {
    }
}