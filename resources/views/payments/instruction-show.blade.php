@extends('layouts.app')

@section('content')
    @php
        $waDigits = preg_replace('/\D+/', '', (string) config('store.whatsapp', ''));
        if (str_starts_with($waDigits, '0')) {
            $waDigits = '62'.substr($waDigits, 1);
        }
        $waText = rawurlencode('Halo '.config('store.name').', saya ingin konfirmasi pembayaran pesanan '.$order->order_number.' (Referensi: '.$payment->reference_code.').');
        $waUrl = $waDigits !== '' ? 'https://wa.me/'.$waDigits.'?text='.$waText : null;
        $bankAccounts = config('store.bank_accounts', []);
    @endphp
    <div class="admin-page">
        <nav class="admin-back-nav" aria-label="Breadcrumb">
            <a class="admin-back-link" href="{{ route('pesanan.show', $order) }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ke detail pesanan</span>
            </a>
        </nav>

        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">{{ config('store.name') }} &bull; Pembayaran</p>
                <h1 class="admin-title">Pembayaran</h1>
                <p class="admin-subtitle">Ikuti instruksi di bawah ini untuk menyelesaikan pembayaran pesananmu dan unggah bukti bayar.</p>
            </div>
            <div class="admin-head-actions">
                <span class="status-chip status-chip-warning">
                    <span>{{ $payment->statusLabel() }}</span>
                </span>
            </div>
        </header>

        <div class="buyer-split-layout">
            <div class="buyer-main-stack">
                <div class="card admin-panel">
                    <div class="pay-method-banner">
                        <div>
                            <p class="admin-eyebrow">Metode Pembayaran</p>
                            <h2 class="admin-section-title" style="margin:0">{{ $method['label'] ?? $payment->payment_method }}</h2>
                        </div>
                    </div>

                    @if (($method['kind'] ?? 'qr') === 'va')
                        <div class="pay-va-box">
                            <h3 class="pay-sub-heading">Nomor Virtual Account</h3>
                            <div class="pay-va-row">
                                <p class="va-number"><strong>{{ $payment->formattedReference() }}</strong></p>
                                <button type="button" class="btn-secondary" data-copy-text="{{ $payment->reference_code }}" data-copy-label="Salin">Salin</button>
                            </div>
                        </div>

                        @if ($payment->qr_url)
                            <div class="pay-qr-section">
                                <h3 class="pay-sub-heading">Kode QR pendamping</h3>
                                <p class="admin-hint">Pindai untuk membuka halaman pembayaran:</p>
                                <div class="qr-small pay-qr-frame" data-qr-url="{{ $payment->qr_url }}">
                                    <img class="qr-image" alt="Kode QR pendamping pembayaran" hidden>
                                    {!! $qrHtml !!}
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="pay-va-box">
                            <h3 class="pay-sub-heading">Kode referensi pembayaran</h3>
                            <div class="pay-va-row">
                                <p class="va-number"><strong>{{ $payment->reference_code }}</strong></p>
                                <button type="button" class="btn-secondary" data-copy-text="{{ $payment->reference_code }}" data-copy-label="Salin">Salin</button>
                            </div>
                        </div>

                        @if ($payment->qr_url)
                            <div class="pay-qr-section">
                                <h3 class="pay-sub-heading">Kode QR</h3>
                                <div class="qr-large pay-qr-frame" data-qr-url="{{ $payment->qr_url }}">
                                    <img class="qr-image" alt="Kode QR pembayaran" width="240" height="240" hidden>
                                    {!! $qrHtml !!}
                                </div>
                                <p class="pay-url-fallback">Atau buka tautan ini: <a class="admin-cell-link" href="{{ $payment->qr_url }}">{{ $payment->qr_url }}</a></p>
                            </div>
                        @endif
                    @endif

                    @if (! empty($bankAccounts))
                        <div class="admin-subcard">
                            <h3 class="pay-sub-heading">Rekening Transfer Toko</h3>
                            @foreach ($bankAccounts as $acc)
                                <p style="margin: 0.25rem 0;">
                                    <strong>{{ $acc['bank'] }}</strong>: <code class="admin-code">{{ $acc['number'] }}</code> a.n. {{ $acc['holder'] }}
                                </p>
                            @endforeach
                        </div>
                    @endif

                    <div class="pay-cta-stack">
                        @if ($payment->qr_url)
                            <a class="btn-primary buyer-cta-block" href="{{ $payment->qr_url }}" target="_blank" rel="noopener">Buka Halaman Pembayaran</a>
                        @else
                            <p class="admin-info-box">Simpan kode referensi di atas dan unggah tangkapan layar/foto bukti pembayaran di bawah agar admin dapat memverifikasi pesananmu.</p>
                        @endif

                        @if ($waUrl)
                            <a class="btn-secondary buyer-cta-block" href="{{ $waUrl }}" target="_blank" rel="noopener">Hubungi Penjual via WhatsApp</a>
                        @endif

                        <a class="btn-secondary buyer-cta-block" href="{{ route('pesanan.show', $order) }}">Kembali ke detail pesanan</a>
                    </div>

                    @if ($payment->qr_url && $isLoopback)
                        <div class="pay-loopback-box">
                            <p><strong>Perhatian:</strong> alamat aplikasi ini adalah loopback (localhost), jadi QR tidak dapat dibuka dari perangkat lain. Gunakan tombol di atas dari perangkat ini.</p>
                        </div>
                    @endif
                </div>

                <section class="card admin-panel" aria-labelledby="upload-proof-heading">
                    <h2 class="admin-section-title" id="upload-proof-heading">Unggah Bukti Pembayaran</h2>
                    @if ($payment->hasProof())
                        <div class="admin-info-box">
                            <strong>Bukti bayar telah diunggah</strong>
                            ({{ $payment->proof_uploaded_at?->setTimezone(config('store.reporting_timezone'))->format('d M Y H:i') }} {{ config('store.timezone_label') }})
                            &mdash; <a class="admin-cell-link" href="{{ route('pembayaran.bukti.show', $payment) }}" target="_blank" rel="noopener">Lihat bukti bayar</a>.
                            Menunggu konfirmasi admin.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('pembayaran.bukti.store', $payment) }}" enctype="multipart/form-data" class="admin-form">
                        @csrf
                        <div class="admin-field">
                            <label for="proof" class="admin-label">
                                {{ $payment->hasProof() ? 'Ganti berkas bukti pembayaran (JPG/PNG/WEBP, maks. 5 MB)' : 'Pilih foto / tangkapan layar bukti bayar (JPG/PNG/WEBP, maks. 5 MB)' }}
                            </label>
                            <input class="input" type="file" id="proof" name="proof" accept="image/jpeg,image/png,image/webp" required>
                            @error('proof')<p class="alert-error field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="admin-form-actions">
                            <button class="btn-primary" type="submit">{{ $payment->hasProof() ? 'Unggah Ulang Bukti Bayar' : 'Unggah Bukti Bayar' }}</button>
                        </div>
                    </form>
                </section>
            </div>

            <aside class="card admin-panel buyer-summary-card" aria-label="Ringkasan pembayaran">
                <h2 class="admin-section-title">Ringkasan pembayaran</h2>
                <dl class="admin-kv-list">
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Nama toko</dt>
                        <dd class="admin-kv-row-val"><strong>{{ config('store.name') }}</strong></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Nomor pesanan</dt>
                        <dd class="admin-kv-row-val"><strong>{{ $order->order_number }}</strong></dd>
                    </div>
                    @if ($order->shipping_cost_idr > 0)
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Subtotal barang</dt>
                            <dd class="admin-kv-row-val">{{ $order->formattedSubtotal() }}</dd>
                        </div>
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Ongkos kirim ({{ $order->courier_name }})</dt>
                            <dd class="admin-kv-row-val">{{ $order->formattedShippingCost() }}</dd>
                        </div>
                    @endif
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Total</dt>
                        <dd class="admin-kv-row-val"><strong class="admin-cell-price">{{ $order->formattedTotal() }}</strong></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Metode</dt>
                        <dd class="admin-kv-row-val"><strong>{{ $method['label'] ?? $payment->payment_method }}</strong></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Kode referensi</dt>
                        <dd class="admin-kv-row-val"><code class="admin-code">{{ $payment->reference_code }}</code></dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Dibuat</dt>
                        <dd class="admin-kv-row-val">{{ $payment->created_at?->setTimezone(config('store.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                    </div>
                    <div class="admin-kv-row">
                        <dt class="admin-kv-row-label">Kedaluwarsa</dt>
                        <dd class="admin-kv-row-val">{{ $payment->expires_at?->setTimezone(config('store.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                    </div>
                </dl>

                @include('payments._pickup-instruction', ['order' => $order])

                <div class="pay-countdown-card">
                    <span class="pay-countdown-label">Sisa waktu bayar</span>
                    <strong class="pay-countdown-timer" id="countdown" data-expires-at="{{ $expiresAtIso }}" data-server-now="{{ $serverNowIso }}" data-poll-url="{{ route('pembayaran.status', $payment) }}">—</strong>
                    <p class="sr-only" aria-live="polite" id="countdown-aria"></p>
                </div>
            </aside>
        </div>
    </div>
    @include('payments._countdown')
@endsection
