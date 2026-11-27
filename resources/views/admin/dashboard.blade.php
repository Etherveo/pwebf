@extends('layouts.app')

@section('content')
    @php
        $tz = $period['tz'];
        $tzLabel = $period['tz_label'];
        $maxUnits = $topProducts->isNotEmpty() ? max(1, (int) $topProducts->max('units_sold')) : 1;
        $maxCatRevenue = $categories->isNotEmpty() ? max(1, (int) $categories->max('revenue_idr')) : 1;
        $statusChips = [
            'menunggu_pembayaran' => 'status-chip-warning',
            'dibayar' => 'status-chip-success',
            'diproses' => 'status-chip-info',
            'selesai' => 'status-chip-success',
            'dibatalkan' => 'status-chip-danger',
            'kedaluwarsa' => 'status-chip-muted',
        ];
        $chartMetrics = [
            'omzet' => 'Omzet (Rp)',
            'unit' => 'Unit Terjual',
            'pesanan' => 'Pesanan Terbayar',
        ];
    @endphp
    <div class="admin-page">
        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">{{ config('store.name') }} &bull; Ringkasan</p>
                <h1 class="admin-title">Dashboard Toko</h1>
                <p class="admin-subtitle">
                    Ringkasan penjualan ({{ $period['label'] }})
                    @if (! $period['is_all_time'])
                        &bull; {{ $period['start_wita']->format('d M Y') }} &ndash; {{ $period['end_wita']->format('d M Y') }} ({{ $tzLabel }})
                    @endif
                    dan antrean pesanan aktif.
                </p>
            </div>
            <div class="admin-head-actions">
                <a class="btn-secondary" href="{{ route('admin.funnel', $filterParams) }}">Funnel Pembelian</a>
                <a class="btn-secondary" href="{{ route('admin.aktivitas', $filterParams) }}">Aktivitas Produk</a>
                <a class="btn-primary" href="{{ route('admin.produk.create') }}">Tambah produk</a>
            </div>
        </header>

        @include('admin._period-filter', [
            'routeName' => 'admin.dashboard',
            'period' => $period,
            'filterParams' => $filterParams,
            'metrics' => $metrics,
            'showSearch' => false,
        ])

        <div class="analytics-kpi-grid">
            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Pendapatan ({{ $period['label'] }})</span>
                    <span class="analytics-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><rect x="2.5" y="4.5" width="15" height="11" rx="2.5" stroke="currentColor" stroke-width="1.6"/><circle cx="10" cy="10" r="2.25" stroke="currentColor" stroke-width="1.6"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">Rp {{ number_format($revenue, 0, ',', '.') }}</span>
                <p class="analytics-note">Total omzet pesanan terbayar</p>
            </div>

            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Pesanan terbayar ({{ $period['label'] }})</span>
                    <span class="analytics-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="M5 3.5H15C15.8284 3.5 16.5 4.17157 16.5 5V16.5L14 15L11.5 16.5L10 15L8.5 16.5L6 15L3.5 16.5V5C3.5 4.17157 4.17157 3.5 5 3.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M7 8H13M7 11H11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ number_format($paidOrders) }}</span>
                <p class="analytics-note">Transaksi lunas terverifikasi</p>
            </div>

            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Unit terjual ({{ $period['label'] }})</span>
                    <span class="analytics-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="M10 2.5L16.5 6.25V13.75L10 17.5L3.5 13.75V6.25L10 2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M3.5 6.25L10 10L16.5 6.25M10 10V17.5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ number_format($unitsSold) }}</span>
                <p class="analytics-note">Akumulasi kuantitas produk</p>
            </div>

            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Perlu diproses / diambil</span>
                    <span class="analytics-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.6"/><path d="M10 6V10L12.5 11.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ number_format($actionableOrders) }}</span>
                <p class="analytics-note">{{ number_format($pendingOrders) }} menunggu pembayaran</p>
            </div>
        </div>

        <section class="card admin-panel" aria-labelledby="dash-sales-chart">
            <div class="analytics-card-head">
                <div>
                    <h2 class="admin-section-title" id="dash-sales-chart">Grafik Penjualan Harian</h2>
                    <p class="analytics-card-sub">
                        Rentang {{ $salesChart['window_start']->format('d M Y') }} &ndash; {{ $salesChart['window_end']->format('d M Y') }} ({{ $tzLabel }})
                    </p>
                </div>
                <div class="analytics-sort-pills" role="group" aria-label="Pilih metrik grafik penjualan">
                    @foreach ($chartMetrics as $metricKey => $metricLabel)
                        <a
                            href="{{ $metrics->buildFilterUrl('admin.dashboard', $filterParams, ['grafik' => $metricKey === 'omzet' ? null : $metricKey]) }}"
                            @class(['btn-ghost-sm', 'is-active' => $grafik === $metricKey])
                            @if ($grafik === $metricKey) aria-current="true" @endif
                        >{{ $metricLabel }}</a>
                    @endforeach
                </div>
            </div>
            <div class="analytics-chart-surface">
                <div class="chart-wrap">
                    <canvas
                        data-chart
                        @if ($salesChart['is_rupiah']) data-chart-rupiah @endif
                        data-chart-config='@json($salesChart['config'])'
                        aria-label="Grafik Penjualan Harian"
                    ></canvas>
                </div>
            </div>
        </section>

        <div class="analytics-split-grid">
            <section class="card admin-panel" aria-labelledby="dash-top-categories">
                <div class="analytics-card-head">
                    <div>
                        <h2 class="admin-section-title" id="dash-top-categories">Ringkasan Kategori</h2>
                        <p class="analytics-card-sub">Kontribusi omzet dan unit terjual per kategori</p>
                    </div>
                    <a class="admin-cell-link" href="{{ route('admin.kategori.index') }}">Kelola kategori</a>
                </div>
                @if ($categories->isEmpty())
                    <div class="admin-empty">
                        <p class="admin-empty-title">Belum ada kategori produk.</p>
                    </div>
                @else
                    <ol class="analytics-list analytics-rank-list">
                        @foreach ($categories as $cat)
                            @php($pct = $cat->revenue_idr > 0 ? round(((int) $cat->revenue_idr / $maxCatRevenue) * 100) : 0)
                            <li class="analytics-rank-item">
                                <div class="analytics-rank-row">
                                    <span class="analytics-rank-name">{{ $cat->name }}</span>
                                    <span class="analytics-rank-meta">
                                        {{ number_format((int) $cat->units_sold) }} unit &middot;
                                        Rp {{ number_format((int) $cat->revenue_idr, 0, ',', '.') }}
                                    </span>
                                </div>
                                <div class="analytics-bar-track" aria-hidden="true">
                                    <span class="analytics-bar-fill" style="width: {{ $cat->revenue_idr > 0 ? max(6, $pct) : 0 }}%"></span>
                                </div>
                                <div class="analytics-rank-foot">
                                    <span class="admin-cell-sub">{{ number_format((int) $cat->paid_orders) }} pesanan terbayar</span>
                                    <div class="analytics-sort-pills">
                                        <a class="btn-ghost-sm analytics-history-pill" href="{{ route('admin.funnel', array_merge($filterParams, ['kategori' => $cat->id])) }}">Funnel</a>
                                        <a class="btn-ghost-sm analytics-history-pill" href="{{ route('admin.aktivitas', array_merge($filterParams, ['kategori' => $cat->id])) }}">Aktivitas</a>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            <section class="card admin-panel" aria-labelledby="dash-top-products">
                <div class="analytics-card-head">
                    <div>
                        <h2 class="admin-section-title" id="dash-top-products">Produk Terlaris</h2>
                        <p class="analytics-card-sub">5 produk dengan unit terjual terbanyak pada periode ini</p>
                    </div>
                    <a class="admin-cell-link" href="{{ route('admin.aktivitas', array_merge($filterParams, ['urut' => 'units_desc'])) }}">Lihat semua</a>
                </div>
                @if ($topProducts->isEmpty())
                    <div class="admin-empty">
                        <p class="admin-empty-title">Belum ada penjualan terbayar.</p>
                        <p class="admin-empty-text">Produk terlaris akan tampil setelah ada pesanan yang berhasil dibayar.</p>
                    </div>
                @else
                    <ol class="analytics-list analytics-rank-list">
                        @foreach ($topProducts as $item)
                            @php($pct = round(((int) $item->units_sold / $maxUnits) * 100))
                            <li class="analytics-rank-item">
                                <div class="analytics-rank-row">
                                    <span class="analytics-rank-name">{{ $item->name }}</span>
                                    <span class="analytics-rank-meta">{{ number_format((int) $item->units_sold) }} unit &middot; Rp {{ number_format((int) $item->revenue_idr, 0, ',', '.') }}</span>
                                </div>
                                <div class="analytics-bar-track" aria-hidden="true">
                                    <span class="analytics-bar-fill" style="width: {{ max(6, $pct) }}%"></span>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>

        <section class="card admin-panel" aria-labelledby="dash-recent-orders">
            <div class="analytics-card-head">
                <div>
                    <h2 class="admin-section-title" id="dash-recent-orders">Pesanan Terbaru</h2>
                    <p class="analytics-card-sub">Daftar pesanan yang masuk ke sistem (5 per halaman)</p>
                </div>
                <a class="admin-cell-link" href="{{ route('admin.pesanan.index') }}">Kelola semua pesanan</a>
            </div>
            @if ($recentOrders->isEmpty())
                <div class="admin-empty">
                    <p class="admin-empty-title">Belum ada pesanan masuk.</p>
                    <p class="admin-empty-text">Pesanan dari pembeli akan langsung tampil di sini.</p>
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Nomor</th>
                                <th>Pembeli</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($recentOrders as $order)
                            <tr>
                                <td data-label="Nomor">
                                    <a class="admin-cell-link" href="{{ route('admin.pesanan.show', $order) }}">{{ $order->order_number }}</a>
                                    <div class="admin-cell-sub">{{ $order->created_at?->setTimezone($tz)->format('d M Y H:i') }} {{ $tzLabel }}</div>
                                </td>
                                <td data-label="Pembeli">{{ $order->user?->name ?? '—' }}</td>
                                <td data-label="Total"><span class="admin-cell-price">{{ $order->formattedTotal() }}</span></td>
                                <td data-label="Status">
                                    <span class="status-chip {{ $statusChips[$order->status] ?? 'status-chip-muted' }}">
                                        <span>{{ $order->statusLabel() }}</span>
                                    </span>
                                </td>
                                <td data-label="Aksi">
                                    <a class="admin-action-link" href="{{ route('admin.pesanan.show', $order) }}">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $recentOrders->links() }}</div>
            @endif
        </section>
    </div>
@endsection
