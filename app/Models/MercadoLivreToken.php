<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MercadoLivreToken extends Model
{
    protected $fillable = [
        'seller_id',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at'    => 'datetime',
        'access_token'  => 'encrypted',
        'refresh_token' => 'encrypted',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->subMinutes(5)->isPast();
    }
}
