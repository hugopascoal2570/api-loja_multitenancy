<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeamstressDistribution extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'cut_id',
        'created_by',
        'notes',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function cut()
    {
        return $this->belongsTo(Cut::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments()
    {
        return $this->hasMany(SeamstressAssignment::class, 'distribution_id');
    }
}
