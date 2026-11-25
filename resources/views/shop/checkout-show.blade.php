@extends('layouts.app')

@section('content')
    @php
        $selectedFulfillment = old('fulfillment_method', 'pickup');
        $couriers = $couriers ?? config('store.couriers', []);
        $addresses = $addresses ?? collect();
    @endphp
    <div class="admin-page">
        <nav class="admin-back-nav" aria-label="Breadcrumb">
            <a class="admin-back-link" href="{{ route('keranjang.index') }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ke keranjang</span>
            </a>
        </nav>

        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">{{ config('store.name') }} &bull; Checkout</p>
                <h1 class="admin-title">Checkout</h1>
                <p class="admin-subtitle">Periksa barang pesananmu, pilih metode pemenuhan (ambil di toko atau kirim ekspedisi), lalu pilih metode pembayaran.</p>
            </div>
        </header>

        <ol class="buyer-step-bar" aria-label="Tahapan pembelian">
            <li class="buyer-step-item is-done">1 Keranjang</li>
            <li class="buyer-step-item is-current" aria-current="step">2 Pembayaran</li>
            <li class="buyer-step-item">3 Hasil</li>
        </ol>

        <form method="POST" action="{{ route('checkout.store') }}" class="buyer-checkout-form" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
            @csrf
            <input type="hidden" name="checkout_key" value="{{ $checkout_key }}">

            <div class="buyer-split-layout">
                <div class="buyer-main-stack">
                    <div class="card admin-panel">
                        <h2 class="admin-section-title">Barang pesanan</h2>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Harga satuan</th>
                                        <th>Jumlah</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($items as $item)
                                    <tr>
                                        <td data-label="Produk" class="admin-cell-primary">
                                            {{ $item->product->name }}
                                            @if ($item->product->is_preorder)
                                                <div class="admin-cell-sub">Pre-Order {{ $item->product->preorder_eta ? '• '.$item->product->preorder_eta : '' }}</div>
                                            @endif
                                        </td>
                                        <td data-label="Harga satuan" class="admin-cell-nowrap">{{ $item->product->formattedPrice() }}</td>
                                        <td data-label="Jumlah">{{ $item->quantity }}</td>
                                        <td data-label="Subtotal" class="admin-cell-price">{{ 'Rp '.number_format($item->subtotalIdr(), 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card admin-panel">
                        <fieldset class="buyer-method-fieldset">
                            <legend class="admin-section-title">Metode pemenuhan pesanan</legend>
                            <p class="admin-hint">Pilih ambil langsung di toko (Rp 0) atau pengiriman lewat kurir ekspedisi.</p>
                            <div class="buyer-method-grid">
                                <label class="buyer-method-card" for="fulfillment-pickup">
                                    <input type="radio" id="fulfillment-pickup" name="fulfillment_method" value="pickup" @checked($selectedFulfillment !== 'delivery') onchange="document.getElementById('box-pickup').hidden = false; document.getElementById('box-delivery').hidden = true;">
                                    <span class="buyer-method-body">
                                        <span class="buyer-method-title">Ambil di Toko (Rp 0)</span>
                                    </span>
                                </label>
                                <label class="buyer-method-card" for="fulfillment-delivery">
                                    <input type="radio" id="fulfillment-delivery" name="fulfillment_method" value="delivery" @checked($selectedFulfillment === 'delivery') onchange="document.getElementById('box-pickup').hidden = true; document.getElementById('box-delivery').hidden = false;">
                                    <span class="buyer-method-body">
                                        <span class="buyer-method-title">Kirim via Ekspedisi</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        <div id="box-pickup" @if ($selectedFulfillment === 'delivery') hidden @endif>
                            <h3 class="admin-section-title">Jadwal pengambilan</h3>
                            <p class="admin-hint">Pesanan diambil di toko {{ config('store.name') }}. Pilih tanggal dan jam pengambilan.</p>
                            <div class="admin-field-grid">
                                <div class="admin-field">
                                    <label for="pickup_date" class="admin-label">Tanggal pengambilan</label>
                                    <input class="input" id="pickup_date" name="pickup_date" type="date" value="{{ old('pickup_date', \Carbon\Carbon::now(config('store.reporting_timezone'))->addDay()->toDateString()) }}"
                                           min="{{ \Carbon\Carbon::now(config('store.reporting_timezone'))->toDateString() }}"
                                           @error('pickup_date') aria-invalid="true" aria-describedby="pickup_date-error" @enderror>
                                    @error('pickup_date')<p class="alert-error field-error" id="pickup_date-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="admin-field">
                                    <label for="pickup_time" class="admin-label">Jam pengambilan (WITA)</label>
                                    <input class="input" id="pickup_time" name="pickup_time" type="time" value="{{ old('pickup_time', '10:00') }}"
                                           @error('pickup_time') aria-invalid="true" aria-describedby="pickup_time-error" @enderror>
                                    @error('pickup_time')<p class="alert-error field-error" id="pickup_time-error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>

                        <div id="box-delivery" @if ($selectedFulfillment !== 'delivery') hidden @endif>
                            <h3 class="admin-section-title">Detail pengiriman ekspedisi</h3>
                            <div class="admin-form">
                                <div class="admin-field">
                                    <label for="courier_code" class="admin-label">Pilih ekspedisi</label>
                                    <select class="input" id="courier_code" name="courier_code">
                                        @foreach ($couriers as $cKey => $courier)
                                            <option value="{{ $cKey }}" @selected(old('courier_code') === $cKey)>
                                                {{ $courier['name'] }} &mdash; Rp {{ number_format((int) $courier['cost_idr'], 0, ',', '.') }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('courier_code')<p class="alert-error field-error">{{ $message }}</p>@enderror
                                </div>

                                @if ($addresses->isNotEmpty())
                                    <div class="admin-field">
                                        <label for="address_id" class="admin-label">Gunakan alamat tersimpan (opsional)</label>
                                        <select class="input" id="address_id" name="address_id">
                                            <option value="">-- Isi alamat baru di bawah --</option>
                                            @foreach ($addresses as $addr)
                                                <option value="{{ $addr->id }}" @selected((string) old('address_id', $addresses->first()->id) === (string) $addr->id)>
                                                    [{{ $addr->address_type }}] {{ $addr->recipient_name }} ({{ $addr->recipient_phone }}) &mdash; {{ $addr->street_address }}, {{ $addr->city }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('address_id')<p class="alert-error field-error">{{ $message }}</p>@enderror
                                    </div>
                                @endif

                                <div class="admin-field-grid">
                                    <div class="admin-field">
                                        <label for="shipping_recipient_name" class="admin-label">Nama penerima</label>
                                        <input class="input" id="shipping_recipient_name" name="shipping_recipient_name" value="{{ old('shipping_recipient_name', $user?->name) }}" placeholder="Nama lengkap penerima">
                                        @error('shipping_recipient_name')<p class="alert-error field-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="admin-field">
                                        <label for="shipping_phone" class="admin-label">Nomor WhatsApp / HP penerima</label>
                                        <input class="input" id="shipping_phone" name="shipping_phone" value="{{ old('shipping_phone', $user?->phone) }}" placeholder="Contoh: 081234567890">
                                        @error('shipping_phone')<p class="alert-error field-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <div class="admin-field">
                                    <label for="shipping_address" class="admin-label">Alamat lengkap pengiriman (jika tidak memilih alamat tersimpan)</label>
                                    <textarea class="input admin-textarea" id="shipping_address" name="shipping_address" rows="3" placeholder="Nama jalan, nomor rumah, kelurahan, kecamatan, kota, kode pos">{{ old('shipping_address') }}</textarea>
                                    @error('shipping_address')<p class="alert-error field-error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="admin-field">
                            <label for="buyer_note" class="admin-label">Catatan pesanan (opsional)</label>
                            <input class="input" id="buyer_note" name="buyer_note" maxlength="500" value="{{ old('buyer_note') }}" placeholder="Catatan tambahan untuk penjual">
                            @error('buyer_note')<p class="alert-error field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="card admin-panel">
                        <fieldset class="buyer-method-fieldset">
                            <legend class="admin-section-title">Pilih metode pembayaran</legend>
                            <p class="admin-hint">Batas waktu bayar setelah pesanan dibuat: {{ $expiry_minutes }} menit.</p>
                            <div class="buyer-method-grid">
                                @foreach ($methods as $method)
                                    <label class="buyer-method-card" for="metode-{{ $method['code'] }}">
                                        <input type="radio" id="metode-{{ $method['code'] }}" name="payment_method" value="{{ $method['code'] }}" @checked($loop->first)>
                                        <span class="buyer-method-body">
                                            <span class="buyer-method-title">{{ $method['label'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                </div>

                <aside class="card admin-panel buyer-summary-card" aria-label="Ringkasan belanja">
                    <h2 class="admin-section-title">Ringkasan belanja</h2>
                    <ul class="buyer-summary-lines">
                        @foreach ($items as $item)
                            <li>
                                <span>{{ $item->product->name }} &times; {{ $item->quantity }}</span>
                                <strong>{{ 'Rp '.number_format($item->subtotalIdr(), 0, ',', '.') }}</strong>
                            </li>
                        @endforeach
                    </ul>
                    <p class="admin-hint">Jika memilih pengiriman ekspedisi, ongkos kirim kurir akan ditambahkan otomatis pada total tagihan pesanan.</p>
                    <p class="buyer-total-line">
                        <span>Subtotal Barang:</span>
                        <strong>{{ 'Rp '.number_format($total_idr, 0, ',', '.') }}</strong>
                    </p>
                    <button class="btn-primary buyer-cta-block" type="submit">Buat Pesanan</button>
                    <a class="btn-secondary buyer-cta-block" href="{{ route('keranjang.index') }}">Kembali ke keranjang</a>
                </aside>
            </div>
        </form>
    </div>
@endsection
