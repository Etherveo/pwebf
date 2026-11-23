@extends('layouts.app')

@section('content')
    @php
        $tz = config('store.reporting_timezone', 'Asia/Makassar');
        $tzLabel = config('store.timezone_label', 'WITA');
        $maxUnits = $topProducts->isNotEmpty() ? max(1, (int) $topProducts->max('units_sold')) : 1;
        $statusChips = [
            'menunggu_pembayaran' => 'status-chip-warning',
            'dibayar' => 'status-chip-success',
            'diproses' => 'status-chip-info',
            'selesai' => 'status-chip-success',
            'dibatalkan' => 'status-chip-danger',
            'kedaluwarsa' => 'status-chip-muted',
        ];
    @endphp
    <div class="admin-page">
        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">{{ config('store.name') }} &bull; Ringkasan</p>
                <h1 class="admin-title">Dashboard Toko</h1>
                <p class="admin-subtitle">Ringkasan penjualan 30 hari terakhir ({{ $startWita->format('d M Y') }} &ndash; {{ $endWita->format('d M Y') }}, {{ $tzLabel }}) dan antrean pesanan aktif.</p>
            </div>
            <div class="admin-head-actions">
                <a class="btn-secondary" href="{{ route('admin.pesanan.index') }}">Kelola pesanan</a>
                <a class="btn-primary" href="{{ route('admin.produk.create') }}">Tambah produk</a>
            </div>
        </header>

        <div class="analytics-kpi-grid">
            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Pendapatan (30 hari)</span>
                    <span class="analytics-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><rect x="2.5" y="4.5" width="15" height="11" rx="2.5" stroke="currentColor" stroke-width="1.6"/><circle cx="10" cy="10" r="2.25" stroke="currentColor" stroke-width="1.6"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">Rp {{ number_format($revenue30d, 0, ',', '.') }}</span>
            </div>

            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Pesanan terbayar (30 hari)</span>
                    <span class="analytics-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="M5 3.5H15C15.8284 3.5 16.5 4.17157 16.5 5V16.5L14 15L11.5 16.5L10 15L8.5 16.5L6 15L3.5 16.5V5C3.5 4.17157 4.17157 3.5 5 3.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M7 8H13M7 11H11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ number_format($paidOrders30d) }}</span>
            </div>

            <div class="card analytics-kpi">
                <div class="analytics-kpi-head">
                    <span class="analytics-kpi-label">Unit terjual (30 hari)</span>
                    <span class="analytics-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="M10 2.5L16.5 6.25V13.75L10 17.5L3.5 13.75V6.25L10 2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M3.5 6.25L10 10L16.5 6.25M10 10V17.5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    </span>
                </div>
                <span class="analytics-kpi-value">{{ number_format($unitsSold30d) }}</span>
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

        <div class="analytics-split-grid">
            <section class="card admin-panel" aria-labelledby="dash-top-products">
                <div class="analytics-card-head">
                    <div>
                        <h2 class="admin-section-title" id="dash-top-products">Produk Terlaris</h2>
                        <p class="analytics-card-sub">5 produk dengan unit terjual terbanyak dalam 30 hari terakhir</p>
                    </div>
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

            <section class="card admin-panel" aria-labelledby="dash-recent-orders">
                <div class="analytics-card-head">
                    <div>
                        <h2 class="admin-section-title" id="dash-recent-orders">Pesanan Terbaru</h2>
                        <p class="analytics-card-sub">5 pesanan terbaru yang masuk ke sistem</p>
                    </div>
                    <a class="admin-cell-link" href="{{ route('admin.pesanan.index') }}">Lihat semua</a>
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
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
