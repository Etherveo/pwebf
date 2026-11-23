<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Append-only buyer activity event recorded natively in Laravel.
class Event extends Model
{
    public const TYPES = ['view', 'add_to_cart', 'booking', 'purchase', 'cancel'];

    public const TYPE_LABELS = [
        'view' => 'Dilihat',
        'add_to_cart' => 'Masuk Keranjang',
        'booking' => 'Booking',
        'purchase' => 'Dibayar',
        'cancel' => 'Batal',
    ];

    public const TYPE_BADGE_CLASSES = [
        'view' => 'is-view',
        'add_to_cart' => 'is-cart',
        'booking' => 'is-booking',
        'purchase' => 'is-purchase',
        'cancel' => 'is-cancel',
    ];

    protected $fillable = [
        'event_type',
        'user_id',
        'product_id',
        'session_ref',
        'order_id',
        'order_item_id',
        'reason',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->event_type] ?? $this->event_type;
    }

    public function badgeClass(): string
    {
        return self::TYPE_BADGE_CLASSES[$this->event_type] ?? '';
    }
}
