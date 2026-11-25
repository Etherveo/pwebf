<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\OrderNumber;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

// M3a ORD-01..05, STK-02, PAY-03, EVT-04. One atomic checkout:
// validate cart -> create order + snapshot items -> reserve stock ->
// booking events -> initial history -> clear cart -> pending payment.
// Any failure rolls the whole transaction back (no partial order, no
// reserved stock, no events, no payment).
//
// Idempotency (ORD-02): checkout_key is generated server-side when the
// checkout page is rendered (GET /checkout) and sent back as a hidden
// form field; re-posting the same key returns the SAME order instead
// of creating a second one. The submit button is also disabled while
// the form is submitting (see the checkout view).
//
// Redirect after success: the order DETAIL page. The payment
// instruction page is M3b (PAY-11); M3a deliberately does not build
// it, so checkout lands on the order, where the "Pembayaran — M3b"
// placeholder button lives (ORD-09).
class CheckoutService
{
    public function __construct(
        private readonly CartService $cart,
        private readonly StockService $stock,
        private readonly OrderStateMachine $orders,
        private readonly PaymentStateMachine $payments,
        private readonly PaymentService $paymentService,
        private readonly ActivityRecorder $activity,
    ) {}

    // ORD-01: the checkout summary data (cart items, server total,
    // blocked items). Delegates to CartService; the controller only
    // decides whether the cart may proceed to checkout at all.
    public function cartView(User $user): array
    {
        return $this->cart->viewFor($user);
    }

    // ORD-05 / PAY-04: expiry minutes from config, default 30. A value
    // outside 1..1440 is a configuration error, not a checkout error.
    public function expiryMinutes(): int
    {
        $minutes = (int) config('store.payment_expiry_minutes', 30);

        if ($minutes < 1 || $minutes > 1440) {
            throw new LogicException('PAYMENT_EXPIRY_MINUTES harus 1..1440 menit, saat ini: '.$minutes.'.');
        }

        return $minutes;
    }

    public function newCheckoutKey(): string
    {
        return (string) Str::uuid();
    }

    public function checkout(
        User $user,
        string $paymentMethod,
        ?string $checkoutKey,
        ?Carbon $pickupAt = null,
        array $fulfillment = [],
    ): Order {
        $checkoutKey = $checkoutKey !== null && trim($checkoutKey) !== ''
            ? trim($checkoutKey)
            : $this->newCheckoutKey();

        return DB::transaction(function () use ($user, $paymentMethod, $checkoutKey, $pickupAt, $fulfillment) {
            // Idempotency first: a re-posted key returns the order that
            // the first post created, even though the cart is empty now.
            $existing = Order::query()
                ->where('user_id', $user->id)
                ->where('checkout_key', $checkoutKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            if (! $this->paymentService->isActiveMethod($paymentMethod)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Metode pembayaran tidak dikenal atau tidak aktif.',
                ]);
            }

            $view = $this->cart->viewFor($user);
            $items = $view['items'];

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'keranjang' => 'Keranjang masih kosong.',
                ]);
            }

            // CRT-05: a blocked item stops checkout entirely.
            foreach ($items as $item) {
                if ($item->isBlocked()) {
                    throw ValidationException::withMessages([
                        'keranjang' => 'Produk “'.$item->product->name.'”: '.($item->blockedReason() ?? 'tidak dapat dipesan.'),
                    ]);
                }
            }

            // ORD-04: total is computed on the server from current
            // prices; any client-supplied amount is ignored.
            $subtotal = 0;
            $lines = [];
            foreach ($items as $item) {
                $price = (int) $item->product->price_idr;
                $lines[] = [
                    'product_id' => $item->product_id,
                    'product_name_snapshot' => $item->product->name,
                    'unit_price_idr' => $price,
                    'quantity' => (int) $item->quantity,
                    'subtotal_idr' => $price * (int) $item->quantity,
                ];
                $subtotal += $price * (int) $item->quantity;
            }
            usort($lines, fn (array $a, array $b) => $a['product_id'] <=> $b['product_id']);

            $fulfillmentMethod = ($fulfillment['fulfillment_method'] ?? 'pickup') === 'delivery'
                ? 'delivery'
                : 'pickup';

            $shippingCost = 0;
            $courierCode = null;
            $courierName = null;
            $recipientName = null;
            $recipientPhone = null;
            $addressSnapshot = null;

            if ($fulfillmentMethod === 'delivery') {
                $code = (string) ($fulfillment['courier_code'] ?? '');
                $courier = config('store.couriers.'.$code);

                if (! is_array($courier)) {
                    throw ValidationException::withMessages([
                        'courier_code' => 'Ekspedisi pengiriman tidak dikenal.',
                    ]);
                }

                $courierCode = $code;
                $courierName = (string) ($courier['name'] ?? strtoupper($code));
                $shippingCost = max(0, (int) ($courier['cost_idr'] ?? 0));

                if (! empty($fulfillment['address_id'])) {
                    $address = Address::query()
                        ->where('user_id', $user->id)
                        ->find((int) $fulfillment['address_id']);

                    if ($address === null) {
                        throw ValidationException::withMessages([
                            'address_id' => 'Alamat pengiriman tidak ditemukan.',
                        ]);
                    }

                    $recipientName = $address->recipient_name;
                    $recipientPhone = $address->recipient_phone;
                    $addressSnapshot = implode(', ', array_filter([
                        $address->street_address,
                        $address->village,
                        $address->district,
                        $address->city,
                        $address->province,
                        $address->postal_code,
                    ]));
                } else {
                    $recipientName = trim((string) ($fulfillment['shipping_recipient_name'] ?? ''));
                    $recipientPhone = trim((string) ($fulfillment['shipping_phone'] ?? ''));
                    $addressSnapshot = trim((string) ($fulfillment['shipping_address'] ?? ''));
                }

                $pickupAt = null;
            }

            $total = $subtotal + $shippingCost;

            $order = Order::create([
                'order_number' => OrderNumber::generate(),
                'user_id' => $user->id,
                'status' => 'menunggu_pembayaran',
                'fulfillment_method' => $fulfillmentMethod,
                'subtotal_idr' => $subtotal,
                'shipping_cost_idr' => $shippingCost,
                'total_idr' => $total,
                'courier_code' => $courierCode,
                'courier_name' => $courierName,
                'shipping_recipient_name' => $recipientName,
                'shipping_phone' => $recipientPhone,
                'shipping_address_snapshot' => $addressSnapshot,
                'buyer_note' => $fulfillment['buyer_note'] ?? null,
                'payment_deadline_at' => now()->addMinutes($this->expiryMinutes()),
                'paid_at' => null,
                'pickup_at' => $pickupAt,
                'checkout_key' => $checkoutKey,
            ]);

            foreach ($lines as $line) {
                OrderItem::create(array_merge($line, [
                    'order_id' => $order->id,
                ]));
            }

            // STK-02: reserve inside the same transaction as the order.
            $order->load('items');
            $this->stock->reserve($order, 'pembeli', $user->id);

            $this->orders->recordInitial($order, 'pembeli', 'pembeli', $user->id);
            $this->activity->recordBooking($order);

            $this->cart->clear($user);

            // PAY-03: one pending payment, expires at the order deadline.
            $this->paymentService->createForOrder($order->fresh(), $paymentMethod);

            return $order->fresh(['items', 'payments']);
        });
    }

    // ORD-07: buyer cancels their own menunggu_pembayaran order.
    // Atomic: order -> dibatalkan, pending payment -> cancelled (via
    // PaymentStateMachine, which also releases stock and writes the
    // cancel events), or — when there is no pending payment (e.g. an
    // earlier payment failed) — the same effects without a payment.
    public function cancelOrder(User $user, Order $order): Order
    {
        if ((int) $order->user_id !== (int) $user->id) {
            // Same response as a missing order (ACC-09).
            throw new ModelNotFoundException();
        }

        return DB::transaction(function () use ($user, $order) {
            $order = $order->fresh(['items']);

            if ($order->status !== 'menunggu_pembayaran') {
                throw ValidationException::withMessages([
                    'status' => 'Pesanan berstatus '.$order->statusLabel().' tidak dapat dibatalkan.',
                ]);
            }

            $payment = $order->payments()->where('status', 'pending')->first();

            if ($payment !== null) {
                $this->payments->markCancelled($payment, $user);
            } else {
                $this->orders->transition($order, 'dibatalkan', 'pembeli', 'pembeli', $user->id, 'buyer');
                $this->stock->release($order, 'pembeli', $user->id);
                $this->activity->recordCancel($order, 'buyer');
            }

            return $order->fresh();
        });
    }
}
