@extends('layouts.app')

@section('content')
    @php
        $funnelSorts = \App\Services\StoreMetricsService::FUNNEL_SORTS;
        $perPageOptions = [10, 15, 25, 50];
    @endphp
    <div class="admin-page">
        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">{{ config('store.name') }} &bull; Ringkasan</p>
                <h1 class="admin-title">Funnel Pembelian</h1>
                <p class="admin-subtitle">
                    Alur konversi pembeli dari melihat produk hingga pembayaran selesai
                    @if ($categoryId !== null)
                        &bull; Kategori: {{ $categories->firstWhere('id', $categoryId)?->name }}
                    @endif
                </p>
            </div>
            <div class="admin-head-actions">
                <a class="btn-secondary" href="{{ route('admin.dashboard') }}">Dashboard</a>
                <a class="btn-secondary" href="{{ route('admin.aktivitas', array_filter(['rentang' => $period['preset'] !== 'custom' ? $period['preset'] : null, 'dari' => $period['preset'] === 'custom' ? $period['dari'] : null, 'sampai' => $period['preset'] === 'custom' ? $period['sampai'] : null, 'kategori' => $categoryId])) }}">Aktivitas Produk</a>
            </div>
        </header>

        @include('admin._period-filter', [
            'routeName' => 'admin.funnel',
            'period' => $period,
            'filterParams' => $filterParams,
            'metrics' => $metrics,
            'categories' => $categories,
            'categoryId' => $categoryId,
            'showSearch' => true,
            'search' => $search,
            'searchPlaceholder' => 'Cari nama produk...',
        ])

        <div class="analytics-kpi-grid">
            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">1. Dilihat</span>
                    <span class="analytics-step-badge">100%</span>
                </div>
                <span class="analytics-kpi-value">{{ number_format($funnel['views']) }}</span>
                <p class="analytics-note">Total tampilan halaman produk pembeli</p>
            </div>

            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">2. Masuk Keranjang</span>
                    <span class="analytics-step-badge">{{ $funnel['view_to_cart_pct'] }}%</span>
                </div>
                <span class="analytics-kpi-value">{{ number_format($funnel['add_to_carts']) }}</span>
                <p class="analytics-note">Rasio dari tahap dilihat</p>
            </div>

            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">3. Booking / Checkout</span>
                    <span class="analytics-step-badge">{{ $funnel['cart_to_booking_pct'] }}%</span>
                </div>
                <span class="analytics-kpi-value">{{ number_format($funnel['bookings']) }}</span>
                <p class="analytics-note">Rasio dari tahap masuk keranjang</p>
            </div>

            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">4. Dibayar</span>
                    <span class="analytics-step-badge">{{ $funnel['booking_to_purchase_pct'] }}%</span>
                </div>
                <span class="analytics-kpi-value">{{ number_format($funnel['purchases']) }}</span>
                <p class="analytics-note">Konversi akhir {{ $funnel['overall_conversion_pct'] }}% &middot; {{ number_format($funnel['cancels']) }} batal</p>
            </div>
        </div>

        <section class="card admin-panel" aria-labelledby="funnel-chart-heading">
            <div class="analytics-card-head">
                <div>
                    <h2 class="admin-section-title" id="funnel-chart-heading">Diagram Tahapan Funnel</h2>
                    <p class="analytics-card-sub">Perbandingan jumlah aktivitas pada tiap langkah pembelian</p>
                </div>
            </div>
            <div class="analytics-chart-surface">
                <div class="chart-wrap">
                    <canvas
                        data-chart
                        data-chart-config='@json($funnel['chart'])'
                        aria-label="Diagram Batang Funnel Pembelian"
                    ></canvas>
                </div>
            </div>
        </section>

        <section class="card admin-panel" aria-labelledby="funnel-table-heading">
            <div class="analytics-card-head">
                <div>
                    <h2 class="admin-section-title" id="funnel-table-heading">Rincian Funnel Per Produk</h2>
                    <p class="analytics-card-sub">Menampilkan {{ number_format($products->total()) }} produk sesuai filter aktif</p>
                </div>
            </div>

            <div class="analytics-filter-top analytics-toolbar-groups">
                <div class="analytics-sort-pills" role="group" aria-label="Urutkan produk funnel">
                    <span class="admin-label">Urutkan:</span>
                    @foreach ($funnelSorts as $sortKey => $sortLabel)
                        <a
                            href="{{ $metrics->buildFilterUrl('admin.funnel', $filterParams, ['urut' => $sortKey]) }}"
                            @class(['btn-ghost-sm', 'is-active' => $sort === $sortKey])
                            @if ($sort === $sortKey) aria-current="true" @endif
                        >{{ $sortLabel }}</a>
                    @endforeach
                </div>

                <div class="analytics-sort-pills" role="group" aria-label="Filter cakupan produk">
                    <a
                        href="{{ $metrics->buildFilterUrl('admin.funnel', $filterParams, ['aktif' => null]) }}"
                        @class(['btn-ghost-sm', 'is-active' => ! $onlyActive])
                        @if (! $onlyActive) aria-current="true" @endif
                    >Semua Produk</a>
                    <a
                        href="{{ $metrics->buildFilterUrl('admin.funnel', $filterParams, ['aktif' => '1']) }}"
                        @class(['btn-ghost-sm', 'is-active' => $onlyActive])
                        @if ($onlyActive) aria-current="true" @endif
                    >Hanya Beraktivitas</a>
                </div>

                <div class="analytics-sort-pills" role="group" aria-label="Pilih jumlah baris per halaman">
                    <span class="admin-label">Per halaman:</span>
                    @foreach ($perPageOptions as $opt)
                        <a
                            href="{{ $metrics->buildFilterUrl('admin.funnel', $filterParams, ['per_page' => $opt === 15 ? null : $opt]) }}"
                            @class(['btn-ghost-sm', 'is-active' => $perPage === $opt])
                            @if ($perPage === $opt) aria-current="true" @endif
                        >{{ $opt }}</a>
                    @endforeach
                </div>
            </div>

            @if ($products->isEmpty())
                <div class="admin-empty">
                    <p class="admin-empty-title">Belum ada data produk pada filter ini.</p>
                    <p class="admin-empty-text">Ubah rentang tanggal, kategori, atau nonaktifkan filter Hanya Beraktivitas.</p>
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th class="admin-num-col">Dilihat</th>
                                <th class="admin-num-col">Keranjang</th>
                                <th class="admin-num-col">Booking</th>
                                <th class="admin-num-col">Dibayar</th>
                                <th class="admin-num-col">Batal</th>
                                <th>Konversi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($products as $row)
                            @php($conv = (float) $row->conversion_rate)
                            <tr>
                                <td data-label="Produk">
                                    <a class="admin-cell-link" href="{{ route('admin.aktivitas.riwayat', ['produk' => $row->id]) }}">{{ $row->name }}</a>
                                    <div class="admin-cell-sub">{{ $row->category_name }}</div>
                                </td>
                                <td data-label="Dilihat" class="admin-num-col">{{ number_format((int) $row->views_count) }}</td>
                                <td data-label="Keranjang" class="admin-num-col">{{ number_format((int) $row->cart_count) }}</td>
                                <td data-label="Booking" class="admin-num-col">{{ number_format((int) $row->booking_count) }}</td>
                                <td data-label="Dibayar" class="admin-num-col">{{ number_format((int) $row->purchase_events_count) }}</td>
                                <td data-label="Batal" class="admin-num-col">{{ number_format((int) $row->cancel_count) }}</td>
                                <td data-label="Konversi">
                                    <div class="analytics-table-bar-cell">
                                        <span class="analytics-inline-bar" aria-hidden="true">
                                            <span class="analytics-inline-bar-fill" style="width: {{ min(100, max(0, $conv)) }}%"></span>
                                        </span>
                                        <span>{{ number_format($conv, 1) }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $products->links() }}</div>
            @endif
        </section>
    </div>
@endsection
