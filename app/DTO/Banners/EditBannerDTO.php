<?php

namespace App\DTO\Banners;

use DateTime;

class EditBannerDTO
{
    public function __construct(
        public string    $id,
        public string    $name,
        public ?string   $description,
        public string    $link,
        public bool      $is_featured,
        public int       $position,
        public string    $image_url,
        public ?DateTime $start_date,
        public ?DateTime $end_date,
        public string $device_type = 'all'
    ) {
    }
}
