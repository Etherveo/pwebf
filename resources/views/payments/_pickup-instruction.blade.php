@if ($order->pickup_at)
    <p class="admin-info-box">
        <strong>Pengambilan di toko.</strong>
        Ambil pesanan di toko {{ config('store.name') }} pada {{ $order->pickup_at?->setTimezone(config('store.reporting_timezone'))->format('d M Y') }}, pukul {{ $order->pickup_at?->setTimezone(config('store.reporting_timezone'))->format('H:i') }} {{ config('store.timezone_label') }}.
    </p>
@endif
