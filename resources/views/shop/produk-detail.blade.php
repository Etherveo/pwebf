@extends('layouts.app')

@section('content')
    @php($habis = $product->stock_available <= 0)
    @php($isBuyer = auth('web')->user()?->isPembeli() === true)
    <div class="shop-page">
        <nav class="shop-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('katalog') }}">Katalog</a>
            <span aria-hidden="true">/</span>
            <span>{{ $product->category?->name }}</span>
        </nav>

        <article class="product-detail">
            <div class="product-detail-art product-art" @if (! $product->hasImage()) aria-hidden="true" @endif>
                <span class="product-art-tag">
                    {{ $product->category?->name }}
                    @if ($product->is_preorder)
                        &bull; Pre-Order
                    @endif
                </span>
                @if ($product->hasImage())
                    <img class="product-detail-img" src="{{ $product->imageUrl() }}" alt="{{ $product->name }}">
                @else
                    <span class="product-art-tile product-art-tile-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 8.7 5 8.7-5"/></svg>
                    </span>
                @endif
            </div>

            <div class="product-detail-info">
                <div class="analytics-sort-pills">
                    <span class="status-chip {{ $product->condition === 'bekas' ? 'status-chip-warning' : 'status-chip-info' }}">
                        <span>Kondisi: {{ $product->conditionLabel() }}</span>
                    </span>
                    @if ($product->is_preorder)
                        <span class="status-chip status-chip-warning">
                            <span>Pre-Order</span>
                        </span>
                    @endif
                    @if ($product->is_featured)
                        <span class="status-chip status-chip-success">
                            <span>Unggulan</span>
                        </span>
                    @endif
                </div>

                <h1 class="shop-title">{{ $product->name }}</h1>
                <p class="product-category">Kategori: {{ $product->category?->name }}</p>
                <p class="product-price product-price-lg">{{ $product->formattedPrice() }}</p>
                @if ($product->is_preorder && $product->preorder_eta)
                    <p class="admin-info-box"><strong>Info Pre-Order:</strong> {{ $product->preorder_eta }}</p>
                @endif
                @if ($product->description)
                    <p class="product-description">{{ $product->description }}</p>
                @endif
                <p @class(['product-stock', 'is-out' => $habis])>Stok tersedia: {{ $habis ? 'Habis' : $product->stock_available }}</p>

                @if (! $habis)
                    @if ($isBuyer)
                        <form method="POST" action="{{ route('keranjang.tambah') }}" class="product-buy">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <div class="product-qty">
                                <label for="jumlah" class="shop-label">Jumlah</label>
                                <div class="stepper" data-stepper>
                                    <button type="button" class="stepper-btn" data-stepper-minus aria-label="Kurangi jumlah" hidden>&minus;</button>
                                    <input class="input stepper-input" id="jumlah" type="number" name="quantity" value="1" min="1" max="99">
                                    <button type="button" class="stepper-btn" data-stepper-plus aria-label="Tambah jumlah" hidden>+</button>
                                </div>
                            </div>
                            <button class="btn-primary" type="submit">Tambah ke Keranjang</button>
                        </form>
                    @else
                        <div class="product-buy">
                            <button class="btn-primary" type="button" data-guest-popup-open="guest-popup" aria-haspopup="dialog">Tambah ke Keranjang</button>
                        </div>
                    @endif
                @else
                    <div class="product-buy">
                        <button class="btn-primary" type="button" disabled>Tambah ke Keranjang</button>
                        <p class="product-out-note">Stok produk ini sedang habis.</p>
                    </div>
                @endif

                <p><a class="admin-back-link" href="{{ route('katalog') }}">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                    <span>Kembali ke katalog</span>
                </a></p>
            </div>
        </article>
    </div>

    @if (! $isBuyer && ! $habis)
        <div class="guest-popup" id="guest-popup" hidden>
            <div class="guest-popup-backdrop" data-guest-popup-close></div>
            <div class="guest-popup-panel" role="dialog" aria-modal="true" aria-labelledby="guest-popup-title" aria-describedby="guest-popup-desc" tabindex="-1">
                <button type="button" class="guest-popup-x" data-guest-popup-close aria-label="Tutup">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
                <h2 id="guest-popup-title" class="guest-popup-title">Masuk dulu untuk membeli</h2>
                <p id="guest-popup-desc" class="guest-popup-text">Kamu perlu masuk atau daftar sebagai pembeli sebelum menambahkan produk ke keranjang.</p>
                <div class="guest-popup-actions">
                    <a class="btn-primary" href="{{ route('masuk') }}">Masuk</a>
                    <a class="btn-secondary" href="{{ route('daftar') }}">Daftar pembeli</a>
                </div>
            </div>
        </div>
    @endif
@endsection
