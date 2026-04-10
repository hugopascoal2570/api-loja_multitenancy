<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Newsletter extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'content',
        'image_path',
        'status',
        'scheduled_at',
        'sent_at',
        'total_recipients',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
