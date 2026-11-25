@extends('layouts.app')

@section('content')
    @php
        $filterAktif = request()->filled('q')
            || request()->filled('kategori')
            || request()->filled('kondisi')
            || request()->filled('tipe')
            || request()->filled('stok')
            || (request()->filled('urut') && request('urut') !== 'nama');
    @endphp
    <div class="shop-page">
        <header class="shop-hero">
            <p class="shop-eyebrow">{{ config('store.name') }} &bull; Katalog</p>
            <h1 class="shop-title">{{ config('store.catalog_headline') }}</h1>
            <p class="shop-lead">{{ config('store.catalog_description') }}</p>
        </header>

        <form method="GET" action="{{ route('katalog') }}" class="shop-search" role="search">
            @if (request()->filled('kategori'))
                <input type="hidden" name="kategori" value="{{ request('kategori') }}">
            @endif
            <label for="q" class="shop-label">Cari produk</label>
            <div class="shop-search-row">
                <div class="input-icon shop-search-input">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input class="input" id="q" name="q" value="{{ request('q') }}" placeholder="Cari produk (minimal 2 karakter)"
                           @error('q') aria-invalid="true" aria-describedby="q-error" @enderror>
                </div>
                <button class="btn-primary" type="submit">Cari</button>
            </div>
            @error('q')<p class="field-error" id="q-error">{{ $message }}</p>@enderror

            <div class="admin-filters" style="margin-top: 0.75rem;">
                <div class="admin-filter-group">
                    <label for="filter-kondisi" class="admin-label">Kondisi</label>
                    <select class="input" id="filter-kondisi" name="kondisi">
                        <option value="">Semua kondisi</option>
                        <option value="baru" @selected(request('kondisi') === 'baru')>Baru</option>
                        <option value="bekas" @selected(request('kondisi') === 'bekas')>Bekas (Preloved)</option>
                    </select>
                </div>

                <div class="admin-filter-group">
                    <label for="filter-tipe" class="admin-label">Tipe ketersediaan</label>
                    <select class="input" id="filter-tipe" name="tipe">
                        <option value="">Ready &amp; Pre-Order</option>
                        <option value="ready" @selected(request('tipe') === 'ready')>Ready Stock</option>
                        <option value="preorder" @selected(request('tipe') === 'preorder')>Pre-Order</option>
                    </select>
                </div>

                <div class="admin-filter-group">
                    <label for="filter-stok" class="admin-label">Stok</label>
                    <select class="input" id="filter-stok" name="stok">
                        <option value="">Semua stok</option>
                        <option value="tersedia" @selected(request('stok') === 'tersedia')>Hanya yang tersedia</option>
                    </select>
                </div>

                <div class="admin-filter-group">
                    <label for="filter-urut" class="admin-label">Urutkan</label>
                    <select class="input" id="filter-urut" name="urut">
                        <option value="nama" @selected(request('urut', 'nama') === 'nama')>Nama (A–Z)</option>
                        <option value="terbaru" @selected(request('urut') === 'terbaru')>Terbaru</option>
                        <option value="harga_asc" @selected(request('urut') === 'harga_asc')>Harga Terendah</option>
                        <option value="harga_desc" @selected(request('urut') === 'harga_desc')>Harga Tertinggi</option>
                    </select>
                </div>

                <div class="admin-filter-actions">
                    <button class="btn-secondary" type="submit">Terapkan Filter</button>
                    @if ($filterAktif)
                        <a class="shop-clear" href="{{ route('katalog') }}">Bersihkan pencarian</a>
                    @endif
                </div>
            </div>
        </form>

        <div class="shop-layout">
            <nav class="shop-categories" aria-label="Kategori">
                <h2 class="shop-categories-title">Kategori</h2>
                <ul>
                    <li>
                        <a href="{{ route('katalog', array_filter(request()->except(['kategori', 'page']))) }}"
                           @class(['shop-category', 'is-active' => ! request()->filled('kategori')])
                           @if (! request()->filled('kategori')) aria-current="page" @endif>Semua kategori</a>
                    </li>
                    @foreach ($categories as $category)
                        @php($aktif = (string) request('kategori') === (string) $category->id)
                        <li>
                            <a href="{{ route('katalog', array_filter(array_merge(request()->except('page'), ['kategori' => $category->id]))) }}"
                               @class(['shop-category', 'is-active' => $aktif])
                               @if ($aktif) aria-current="page" @endif>{{ $category->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <section aria-label="Daftar produk">
                @if (request()->filled('q'))
                    <p class="shop-result-note">Hasil pencarian untuk: {{ request('q') }}</p>
                @endif

                @if ($products->isEmpty())
                    <div class="shop-empty">
                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        <p class="shop-empty-title">Tidak ada produk yang ditemukan.</p>
                        @if ($filterAktif)
                            <p>Periksa ejaan atau coba kategori lain. <a href="{{ route('katalog') }}">Bersihkan pencarian</a> untuk melihat semua produk.</p>
                        @else
                            <p>Produk akan tampil di sini setelah ditambahkan oleh toko.</p>
                        @endif
                    </div>
                @else
                    <ul class="product-grid">
                        @foreach ($products as $product)
                            @php($habis = $product->stock_available <= 0)
                            <li class="product-card">
                                <a class="product-card-link" href="{{ route('produk.show', $product) }}">
                                    <div class="product-art" aria-hidden="true">
                                        <span class="product-art-tag">
                                            {{ $product->category?->name }}
                                            @if ($product->is_preorder)
                                                &bull; Pre-Order
                                            @elseif ($product->condition === 'bekas')
                                                &bull; Bekas
                                            @endif
                                        </span>
                                        @if ($product->hasImage())
                                            <img src="{{ $product->imageUrl() }}" alt="" style="width: 100%; height: 100%; object-fit: cover; border-radius: inherit;">
                                        @else
                                            <span class="product-art-tile">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 8.7 5 8.7-5"/></svg>
                                            </span>
                                        @endif
                                    </div>
                                    <h2 class="product-name">{{ $product->name }}</h2>
                                    <p class="product-category">
                                        {{ $product->category?->name }}
                                        &bull; {{ $product->conditionLabel() }}
                                    </p>
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
                    <div class="shop-pagination">{{ $products->links() }}</div>
                @endif
            </section>
        </div>
    </div>
@endsection
