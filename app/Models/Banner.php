<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banner extends Model
{
    use HasUuids,SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string'; 

    protected $fillable = [
        'name',
        'description',
        'link',
        'is_featured',
        'position',
        'image_url',
        'start_date',
        'end_date',
        'device_type',
        'active',
    ];


    protected $casts = [
        'is_featured' => 'boolean',
        'start_date'  => 'datetime',
        'end_date'    => 'datetime',
        'active'      => 'boolean',
    ];

    public function scopeActive($query)
{
    return $query->where(function ($q) {
        $q->whereNull('end_date')
          ->orWhere('end_date', '>=', now());
    });
}
}