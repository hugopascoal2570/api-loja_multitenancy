<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Cart extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'uuid';
    protected $table = 'carts';
    protected $fillable = ['id', 'user_id', 'token', 'status'];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function user()
    {
    return $this->belongsTo(User::class);
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

}

