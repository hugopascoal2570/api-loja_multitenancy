<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
        'default_value',
        'type',
        'group',
        'description',
        'label',
        'options',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    public $incrementing = false;
    protected $keyType = 'string';
}
