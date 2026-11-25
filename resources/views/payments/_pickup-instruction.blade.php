@if ($order->isDelivery())
    <p class="admin-info-box">
        <strong>Pengiriman Ekspedisi ({{ $order->courier_name ?? strtoupper((string) $order->courier_code) }}).</strong>
        Penerima: {{ $order->shipping_recipient_name ?? $order->user?->name }} ({{ $order->shipping_phone ?? '—' }}) &mdash;
        {{ $order->shipping_address_snapshot }}
        @if ($order->tracking_number)
            <br><strong>Nomor Resi:</strong> <code class="admin-code">{{ $order->tracking_number }}</code>
        @endif
    </p>
@elseif ($order->pickup_at)
    <p class="admin-info-box">
        <strong>Pengambilan di toko.</strong>
        Ambil pesanan di toko {{ config('store.name') }} pada {{ $order->pickup_at?->setTimezone(config('store.reporting_timezone'))->format('d M Y') }}, pukul {{ $order->pickup_at?->setTimezone(config('store.reporting_timezone'))->format('H:i') }} {{ config('store.timezone_label') }}.
    </p>
@endif
