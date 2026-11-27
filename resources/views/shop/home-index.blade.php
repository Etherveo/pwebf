@extends('layouts.app')

@section('content')
    {{-- Tiga section produk aktif: Terlaris (disembunyikan bila belum ada produk
         memenuhi threshold 3 unit berbayar dalam 30 hari terakhir), Terbaru, dan
         Teratas (dengan tombol "Lihat selengkapnya" ke /katalog). --}}
    <div class="shop-page">
        <header class="shop-hero home-hero">
            <div class="home-hero-copy">
                <p class="shop-eyebrow">{{ config('store.name') }}{{ config('store.location') ? ' • '.config('store.location') : '' }}</p>
                <h1 class="shop-title">{{ config('store.hero_headline') }}</h1>
                <p class="shop-lead">{{ config('store.hero_description') }}</p>
                <ul class="home-hero-highlights" aria-label="Keunggulan toko">
                    <li>Stok real-time transparan</li>
                    <li>Ambil di toko atau kirim ekspedisi</li>
                    <li>Ready stock &amp; pre-order</li>
                </ul>
            </div>
            <div class="home-hero-actions">
                <div class="home-hero-panel" aria-hidden="true">
                    <span class="home-hero-tile">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    </span>
                    <div class="home-hero-panel-meta">
                        <strong>{{ config('store.name') }}</strong>
                        <span>{{ config('store.tagline') }}</span>
                    </div>
                </div>
                <a class="btn-hero" href="{{ route('katalog') }}">
                    <span>Jelajahi Katalog</span>
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>
        </header>

        @if ($bestSellers->isNotEmpty())
            <section class="home-section" aria-labelledby="section-terlaris">
                <div class="home-section-head">
                    <div class="home-section-heading">
                        <p class="shop-eyebrow">Pilihan populer</p>
                        <h2 id="section-terlaris" class="home-section-title">Terlaris</h2>
                    </div>
                    <span class="admin-count-badge">{{ $bestSellers->count() }} produk</span>
                </div>
                <ul class="product-grid">
                    @foreach ($bestSellers as $product)
                        @php($habis = $product->stock_available <= 0)
                        <li @class(['product-card', 'is-out' => $habis])>
                            <a class="product-card-link" href="{{ route('produk.show', $product) }}">
                                <div class="product-art" aria-hidden="true">
                                    <span class="product-art-tag">{{ $product->category?->name }}</span>
                                    @if ($product->is_featured || $product->is_preorder || $product->condition === 'bekas' || $habis)
                                        <div class="product-art-badges">
                                            @if ($product->is_featured)
                                                <span class="status-chip status-chip-success">Unggulan</span>
                                            @endif
                                            @if ($product->is_preorder)
                                                <span class="status-chip status-chip-warning">Pre-Order</span>
                                            @endif
                                            @if ($product->condition === 'bekas')
                                                <span class="status-chip status-chip-info">Bekas</span>
                                            @endif
                                            @if ($habis)
                                                <span class="status-chip status-chip-danger">Habis</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($product->hasImage())
                                        <img class="product-art-img" src="{{ $product->imageUrl() }}" alt="">
                                    @else
                                        <span class="product-art-tile">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 8.7 5 8.7-5"/></svg>
                                        </span>
                                    @endif
                                </div>
                                <h3 class="product-name">{{ $product->name }}</h3>
                                <p class="product-category">{{ $product->category?->name }} &bull; {{ $product->conditionLabel() }}</p>
                                <p class="product-price">{{ $product->formattedPrice() }}</p>
                                <p @class(['product-stock', 'is-out' => $habis])>
                                    @if ($habis)
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                                        Habis
                                    @else
                                        Tersedia: {{ $product->stock_available }}
                                    @endif
                                </p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="home-section" aria-labelledby="section-terbaru">
            <div class="home-section-head">
                <div class="home-section-heading">
                    <p class="shop-eyebrow">Baru ditambahkan</p>
                    <h2 id="section-terbaru" class="home-section-title">Terbaru</h2>
                </div>
                @if ($newestProducts->isNotEmpty())
                    <span class="admin-count-badge">{{ $newestProducts->count() }} produk</span>
                @endif
            </div>
            @if ($newestProducts->isEmpty())
                <div class="shop-empty">
                    <p class="shop-empty-title">Belum ada produk aktif.</p>
                </div>
            @else
                <ul class="product-grid">
                    @foreach ($newestProducts as $product)
                        @php($habis = $product->stock_available <= 0)
                        <li @class(['product-card', 'is-out' => $habis])>
                            <a class="product-card-link" href="{{ route('produk.show', $product) }}">
                                <div class="product-art" aria-hidden="true">
                                    <span class="product-art-tag">{{ $product->category?->name }}</span>
                                    @if ($product->is_featured || $product->is_preorder || $product->condition === 'bekas' || $habis)
                                        <div class="product-art-badges">
                                            @if ($product->is_featured)
                                                <span class="status-chip status-chip-success">Unggulan</span>
                                            @endif
                                            @if ($product->is_preorder)
                                                <span class="status-chip status-chip-warning">Pre-Order</span>
                                            @endif
                                            @if ($product->condition === 'bekas')
                                                <span class="status-chip status-chip-info">Bekas</span>
                                            @endif
                                            @if ($habis)
                                                <span class="status-chip status-chip-danger">Habis</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($product->hasImage())
                                        <img class="product-art-img" src="{{ $product->imageUrl() }}" alt="">
                                    @else
                                        <span class="product-art-tile">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 8.7 5 8.7-5"/></svg>
                                        </span>
                                    @endif
                                </div>
                                <h3 class="product-name">{{ $product->name }}</h3>
                                <p class="product-category">{{ $product->category?->name }} &bull; {{ $product->conditionLabel() }}</p>
                                <p class="product-price">{{ $product->formattedPrice() }}</p>
                                <p @class(['product-stock', 'is-out' => $habis])>
                                    @if ($habis)
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                                        Habis
                                    @else
                                        Tersedia: {{ $product->stock_available }}
                                    @endif
                                </p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="home-section" aria-labelledby="section-teratas">
            <div class="home-section-head">
                <div class="home-section-heading">
                    <p class="shop-eyebrow">Katalog utama</p>
                    <h2 id="section-teratas" class="home-section-title">Teratas</h2>
                </div>
                <a class="btn-secondary home-more-btn" href="{{ route('katalog') }}">
                    <span>Lihat selengkapnya</span>
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>
            @if ($topProducts->isEmpty())
                <div class="shop-empty">
                    <p class="shop-empty-title">Belum ada produk aktif.</p>
                </div>
            @else
                <ul class="product-grid">
                    @foreach ($topProducts as $product)
                        @php($habis = $product->stock_available <= 0)
                        <li @class(['product-card', 'is-out' => $habis])>
                            <a class="product-card-link" href="{{ route('produk.show', $product) }}">
                                <div class="product-art" aria-hidden="true">
                                    <span class="product-art-tag">{{ $product->category?->name }}</span>
                                    @if ($product->is_featured || $product->is_preorder || $product->condition === 'bekas' || $habis)
                                        <div class="product-art-badges">
                                            @if ($product->is_featured)
                                                <span class="status-chip status-chip-success">Unggulan</span>
                                            @endif
                                            @if ($product->is_preorder)
                                                <span class="status-chip status-chip-warning">Pre-Order</span>
                                            @endif
                                            @if ($product->condition === 'bekas')
                                                <span class="status-chip status-chip-info">Bekas</span>
                                            @endif
                                            @if ($habis)
                                                <span class="status-chip status-chip-danger">Habis</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($product->hasImage())
                                        <img class="product-art-img" src="{{ $product->imageUrl() }}" alt="">
                                    @else
                                        <span class="product-art-tile">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 8.7 5 8.7-5"/></svg>
                                        </span>
                                    @endif
                                </div>
                                <h3 class="product-name">{{ $product->name }}</h3>
                                <p class="product-category">{{ $product->category?->name }} &bull; {{ $product->conditionLabel() }}</p>
                                <p class="product-price">{{ $product->formattedPrice() }}</p>
                                <p @class(['product-stock', 'is-out' => $habis])>
                                    @if ($habis)
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                                        Habis
                                    @else
                                        Tersedia: {{ $product->stock_available }}
                                    @endif
                                </p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
