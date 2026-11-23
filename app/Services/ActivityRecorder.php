<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;

// Records native SQL storefront & order lifecycle counters (view, add_to_cart,
// booking, purchase, cancel) without Python or external telemetry services.
class ActivityRecorder
{
    public function recordView(User $buyer, Product $product, ?string $sessionRef = null, ?Carbon $at = null): ?Event
    {
        if (! $buyer->isPembeli() || ! $buyer->is_active || ! $product->is_active) {
            return null;
        }

        $stamp = $at ?? now();
        $dedupMinutes = max(1, (int) config('store.view_event_dedup_minutes', 30));

        $recentExists = Event::query()
            ->where('event_type', 'view')
            ->where('user_id', $buyer->id)
            ->where('product_id', $product->id)
            ->whereBetween('occurred_at', [$stamp->copy()->subMinutes($dedupMinutes), $stamp])
            ->exists();

        if ($recentExists) {
            return null;
        }

        return Event::create([
            'event_type' => 'view',
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'session_ref' => $sessionRef,
            'occurred_at' => $stamp,
        ]);
    }

    public function recordAddToCart(User $buyer, Product $product, ?string $sessionRef = null, ?Carbon $at = null): ?Event
    {
        if (! $buyer->isPembeli() || ! $buyer->is_active) {
            return null;
        }

        return Event::create([
            'event_type' => 'add_to_cart',
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'session_ref' => $sessionRef,
            'occurred_at' => $at ?? now(),
        ]);
    }

    public function recordBooking(Order $order, ?Carbon $at = null): void
    {
        $this->recordOrderLifecycle($order, 'booking', null, $at);
    }

    public function recordPurchase(Order $order, ?Carbon $at = null): void
    {
        $this->recordOrderLifecycle($order, 'purchase', null, $at);
    }

    public function recordCancel(Order $order, string $reason, ?Carbon $at = null): void
    {
        $this->recordOrderLifecycle($order, 'cancel', $reason, $at);
    }

    private function recordOrderLifecycle(Order $order, string $eventType, ?string $reason, ?Carbon $at): void
    {
        $order->loadMissing('items');
        $stamp = $at ?? now();

        foreach ($order->items as $item) {
            $alreadyRecorded = Event::query()
                ->where('order_item_id', $item->id)
                ->where('event_type', $eventType)
                ->exists();

            if ($alreadyRecorded) {
                continue;
            }

            Event::create([
                'event_type' => $eventType,
                'user_id' => $order->user_id,
                'product_id' => $item->product_id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'reason' => $reason,
                'occurred_at' => $stamp,
            ]);
        }
    }
}
