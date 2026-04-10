<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'order_number',
        'user_id',
        'total_amount',
        'delivery_fee',
        'delivery_method',
        'shipping_address',
        'shipping_number',
        'shipping_neighborhood',
        'shipping_complement',
        'shipping_city',
        'shipping_state',
        'shipping_zip_code',
        'shipping_recipient_name',
        'shipping_phone',
        'shipping_service_id',
        'shipping_service_name',
        'shipping_estimated_days',
        'tracking_code',
        'shipping_status',
        'melhor_envio_order_id',
        'melhor_envio_protocol',
        'melhor_envio_label_url',
        'melhor_envio_paid_at',
        'melhor_envio_generated_at',
        'melhor_envio_posted_at',
        'melhor_envio_delivered_at',
        'coupon_id',
        'coupon_code',
        'discount_amount',
        'excursion_info',
        'payment_method',
        'payment_id',
        'status',
        'source',
        'customer_name',
        'customer_phone',
        'refund_id',
        'refund_amount',
        'canceled_at',
        'cancel_reason',
        'ml_order_id',
        'ml_shipment_id',
        'ml_content_declared_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'canceled_at' => 'datetime',
        'ml_content_declared_at' => 'datetime',
        'melhor_envio_paid_at' => 'datetime',
        'melhor_envio_generated_at' => 'datetime',
        'melhor_envio_posted_at' => 'datetime',
        'melhor_envio_delivered_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class); // chave estrangeira: user_id
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class); // chave estrangeira: cart_id
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class); // se você usa order_items
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

}
