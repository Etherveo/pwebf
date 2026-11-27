@extends('layouts.app')

@section('content')
    @php
        $activitySorts = \App\Services\StoreMetricsService::ACTIVITY_SORTS;
        $perPageOptions = [10, 15, 25, 50];
        $tabParams = array_filter([
            'rentang' => $period['preset'] !== 'custom' ? $period['preset'] : null,
            'dari' => $period['preset'] === 'custom' ? $period['dari'] : null,
            'sampai' => $period['preset'] === 'custom' ? $period['sampai'] : null,
            'kategori' => $categoryId,
        ], static fn ($v) => $v !== null && $v !== '');
    @endphp
    <div class="admin-page">
        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">{{ config('store.name') }} &bull; Ringkasan</p>
                <h1 class="admin-title">Aktivitas Produk</h1>
                <p class="admin-subtitle">
                    Counter jumlah dilihat, masuk keranjang, booking, dan pembelian per produk secara real-time.
                </p>
            </div>
            <nav class="analytics-quick" aria-label="Mode tampilan aktivitas">
                <a href="{{ route('admin.aktivitas', $tabParams) }}" class="btn-secondary analytics-quick-pill" aria-current="true">Rekap Per Produk</a>
                <a href="{{ route('admin.aktivitas.riwayat', $tabParams) }}" class="btn-secondary analytics-quick-pill">Riwayat Aktivitas</a>
            </nav>
        </header>

        @include('admin._period-filter', [
            'routeName' => 'admin.aktivitas',
            'period' => $period,
            'filterParams' => $filterParams,
            'metrics' => $metrics,
            'categories' => $categories,
            'categoryId' => $categoryId,
            'showSearch' => true,
            'search' => $search,
            'searchPlaceholder' => 'Cari nama produk...',
        ])

        <section class="card admin-panel" aria-labelledby="activity-chart-heading">
            <div class="analytics-card-head">
                <div>
                    <h2 class="admin-section-title" id="activity-chart-heading">Grafik Aktivitas Harian</h2>
                    <p class="analytics-card-sub">
                        Pergerakan harian dilihat, masuk keranjang, dan dibayar ({{ $period['tz_label'] }})
                    </p>
                </div>
            </div>
            <div class="analytics-chart-surface">
                <div class="chart-wrap">
                    <canvas
                        data-chart
                        data-chart-config='@json($dailyChart)'
                        aria-label="Grafik Aktivitas Harian Produk"
                    ></canvas>
                </div>
            </div>
        </section>

        <section class="card admin-panel" aria-labelledby="activity-table-heading">
            <div class="analytics-card-head">
                <div>
                    <h2 class="admin-section-title" id="activity-table-heading">Counter Aktivitas Per Produk</h2>
                    <p class="analytics-card-sub">Menampilkan {{ number_format($products->total()) }} produk pada filter aktif</p>
                </div>
            </div>

            <div class="analytics-filter-top analytics-toolbar-groups">
                <div class="analytics-sort-pills" role="group" aria-label="Urutkan counter produk">
                    <span class="admin-label">Urutkan:</span>
                    @foreach ($activitySorts as $sortKey => $sortLabel)
                        <a
                            href="{{ $metrics->buildFilterUrl('admin.aktivitas', $filterParams, ['urut' => $sortKey]) }}"
                            @class(['btn-ghost-sm', 'is-active' => $sort === $sortKey])
                            @if ($sort === $sortKey) aria-current="true" @endif
                        >{{ $sortLabel }}</a>
                    @endforeach
                </div>

                <div class="analytics-sort-pills" role="group" aria-label="Filter cakupan produk">
                    <a
                        href="{{ $metrics->buildFilterUrl('admin.aktivitas', $filterParams, ['aktif' => null]) }}"
                        @class(['btn-ghost-sm', 'is-active' => ! $onlyActive])
                        @if (! $onlyActive) aria-current="true" @endif
                    >Semua Produk</a>
                    <a
                        href="{{ $metrics->buildFilterUrl('admin.aktivitas', $filterParams, ['aktif' => '1']) }}"
                        @class(['btn-ghost-sm', 'is-active' => $onlyActive])
                        @if ($onlyActive) aria-current="true" @endif
                    >Hanya Beraktivitas</a>
                </div>

                <div class="analytics-sort-pills" role="group" aria-label="Pilih jumlah baris per halaman">
                    <span class="admin-label">Per halaman:</span>
                    @foreach ($perPageOptions as $opt)
                        <a
                            href="{{ $metrics->buildFilterUrl('admin.aktivitas', $filterParams, ['per_page' => $opt === 15 ? null : $opt]) }}"
                            @class(['btn-ghost-sm', 'is-active' => $perPage === $opt])
                            @if ($perPage === $opt) aria-current="true" @endif
                        >{{ $opt }}</a>
                    @endforeach
                </div>
            </div>

            @if ($products->isEmpty())
                <div class="admin-empty">
                    <p class="admin-empty-title">Belum ada aktivitas produk pada filter ini.</p>
                    <p class="admin-empty-text">Coba perluas rentang waktu, pilih kategori lain, atau tampilkan semua produk.</p>
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table admin-table-dense">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th class="admin-num-col">Dilihat</th>
                                <th class="admin-num-col">Keranjang</th>
                                <th class="admin-num-col">Booking</th>
                                <th class="admin-num-col">Dibayar</th>
                                <th class="admin-num-col">Batal</th>
                                <th class="admin-num-col">Unit Terjual</th>
                                <th class="admin-num-col">Omzet</th>
                                <th class="admin-num-col">Konversi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($products as $row)
                            <tr>
                                <td data-label="Produk">
                                    <span class="admin-cell-primary">{{ $row->name }}</span>
                                    <div class="admin-cell-sub">{{ $row->category_name }}</div>
                                </td>
                                <td data-label="Dilihat" class="admin-num-col">{{ number_format((int) $row->views_count) }}</td>
                                <td data-label="Keranjang" class="admin-num-col">{{ number_format((int) $row->cart_count) }}</td>
                                <td data-label="Booking" class="admin-num-col">{{ number_format((int) $row->booking_count) }}</td>
                                <td data-label="Dibayar" class="admin-num-col">{{ number_format((int) $row->purchase_events_count) }}</td>
                                <td data-label="Batal" class="admin-num-col">{{ number_format((int) $row->cancel_count) }}</td>
                                <td data-label="Unit Terjual" class="admin-num-col">{{ number_format((int) $row->units_sold) }}</td>
                                <td data-label="Omzet" class="admin-num-col"><span class="admin-cell-price">Rp {{ number_format((int) $row->revenue_idr, 0, ',', '.') }}</span></td>
                                <td data-label="Konversi" class="admin-num-col">{{ number_format((float) $row->conversion_rate, 1) }}%</td>
                                <td data-label="Aksi">
                                    <a class="admin-action-link" href="{{ route('admin.aktivitas.riwayat', array_merge($tabParams, ['produk' => $row->id])) }}">Riwayat</a>
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
