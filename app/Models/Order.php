<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// M3a ORD-01..14, blueprint §6.2. Statuses (PRD §9.2):
// menunggu_pembayaran, dibayar, diproses, selesai, dibatalkan, kedaluwarsa.
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const STATUSES = [
        'menunggu_pembayaran', 'dibayar', 'diproses',
        'selesai', 'dibatalkan', 'kedaluwarsa',
    ];

    public const STATUS_LABELS = [
        'menunggu_pembayaran' => 'Menunggu Pembayaran',
        'dibayar' => 'Dibayar',
        'diproses' => 'Siap Diambil',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
        'kedaluwarsa' => 'Kedaluwarsa',
    ];

    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'fulfillment_method',
        'subtotal_idr',
        'shipping_cost_idr',
        'total_idr',
        'courier_code',
        'courier_name',
        'shipping_recipient_name',
        'shipping_phone',
        'shipping_address_snapshot',
        'tracking_number',
        'buyer_note',
        'payment_deadline_at',
        'paid_at',
        'picked_up_at',
        'pickup_at',
        'checkout_key',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_idr' => 'integer',
            'shipping_cost_idr' => 'integer',
            'total_idr' => 'integer',
            'payment_deadline_at' => 'datetime',
            'paid_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'pickup_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function pendingPayment(): ?Payment
    {
        return $this->payments->firstWhere('status', 'pending')
            ?? $this->payments()->where('status', 'pending')->first();
    }

    public function isDelivery(): bool
    {
        return ($this->fulfillment_method ?? 'pickup') === 'delivery';
    }

    public function fulfillmentLabel(): string
    {
        return $this->isDelivery() ? 'Pengiriman Ekspedisi' : 'Ambil di Toko';
    }

    public function statusLabel(): string
    {
        if ($this->status === 'diproses' && $this->isDelivery()) {
            return 'Sedang Dikirim';
        }

        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function formattedSubtotal(): string
    {
        $sub = $this->subtotal_idr ?? max(0, (int) $this->total_idr - (int) $this->shipping_cost_idr);

        return 'Rp '.number_format((int) $sub, 0, ',', '.');
    }

    public function formattedShippingCost(): string
    {
        return 'Rp '.number_format((int) $this->shipping_cost_idr, 0, ',', '.');
    }

    public function formattedTotal(): string
    {
        return 'Rp '.number_format((int) $this->total_idr, 0, ',', '.');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['dibatalkan', 'kedaluwarsa', 'selesai'], true);
    }
}
