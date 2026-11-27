@extends('layouts.app')

@section('content')
    @php
        $tz = $period['tz'];
        $tzLabel = $period['tz_label'];
        $eventTypes = \App\Models\Event::TYPE_LABELS;
        $perPageOptions = [15, 20, 50];
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
                <h1 class="admin-title">Riwayat Aktivitas Pembeli</h1>
                <p class="admin-subtitle">
                    Catatan aktivitas pembelian secara kronologis ({{ $tzLabel }}), dapat disaring per jenis event, produk, dan tanggal.
                </p>
            </div>
            <nav class="analytics-quick" aria-label="Mode tampilan aktivitas">
                <a href="{{ route('admin.aktivitas', $tabParams) }}" class="btn-secondary analytics-quick-pill">Rekap Per Produk</a>
                <a href="{{ route('admin.aktivitas.riwayat', $tabParams) }}" class="btn-secondary analytics-quick-pill" aria-current="true">Riwayat Aktivitas</a>
            </nav>
        </header>

        @include('admin._period-filter', [
            'routeName' => 'admin.aktivitas.riwayat',
            'period' => $period,
            'filterParams' => $filterParams,
            'metrics' => $metrics,
            'categories' => $categories,
            'categoryId' => $categoryId,
            'showSearch' => true,
            'search' => $search,
            'searchPlaceholder' => 'Cari nama produk atau pembeli...',
        ])

        <section class="card admin-panel" aria-labelledby="history-table-heading">
            <div class="analytics-card-head">
                <div>
                    <h2 class="admin-section-title" id="history-table-heading">Log Event Aktivitas</h2>
                    <p class="analytics-card-sub">Ditemukan {{ number_format($events->total()) }} event aktivitas</p>
                </div>
            </div>

            <div class="analytics-filter-top analytics-toolbar-groups">
                <div class="analytics-sort-pills" role="group" aria-label="Filter jenis aktivitas">
                    <span class="admin-label">Jenis:</span>
                    <a
                        href="{{ $metrics->buildFilterUrl('admin.aktivitas.riwayat', $filterParams, ['jenis' => null]) }}"
                        @class(['btn-ghost-sm', 'is-active' => $eventType === null])
                        @if ($eventType === null) aria-current="true" @endif
                    >Semua</a>
                    @foreach ($eventTypes as $typeKey => $typeLabel)
                        <a
                            href="{{ $metrics->buildFilterUrl('admin.aktivitas.riwayat', $filterParams, ['jenis' => $typeKey]) }}"
                            @class(['btn-ghost-sm', 'is-active' => $eventType === $typeKey])
                            @if ($eventType === $typeKey) aria-current="true" @endif
                        >{{ $typeLabel }}</a>
                    @endforeach
                </div>

                <div class="analytics-sort-pills" role="group" aria-label="Pilih jumlah baris riwayat per halaman">
                    <span class="admin-label">Per halaman:</span>
                    @foreach ($perPageOptions as $opt)
                        <a
                            href="{{ $metrics->buildFilterUrl('admin.aktivitas.riwayat', $filterParams, ['per_page' => $opt === 20 ? null : $opt]) }}"
                            @class(['btn-ghost-sm', 'is-active' => $perPage === $opt])
                            @if ($perPage === $opt) aria-current="true" @endif
                        >{{ $opt }}</a>
                    @endforeach
                </div>
            </div>

            <form method="GET" action="{{ route('admin.aktivitas.riwayat') }}" class="admin-filters">
                @foreach (collect($filterParams)->except(['produk', 'page'])->all() as $key => $val)
                    <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                @endforeach
                <div class="admin-filter-group admin-filter-grow">
                    <label for="filter-produk" class="admin-label">Saring produk tertentu</label>
                    <select class="input" id="filter-produk" name="produk">
                        <option value="">Semua produk</option>
                        @foreach ($allProducts as $prod)
                            <option value="{{ $prod->id }}" @selected($productId === (int) $prod->id)>{{ $prod->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter-actions">
                    <button class="btn-secondary" type="submit">Pilih Produk</button>
                </div>
            </form>

            @if ($events->isEmpty())
                <div class="admin-empty">
                    <p class="admin-empty-title">Belum ada catatan aktivitas untuk filter ini.</p>
                    <p class="admin-empty-text">Aktivitas otomatis tercatat saat pembeli melihat produk, menambah keranjang, checkout, membayar, atau membatalkan pesanan.</p>
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Waktu ({{ $tzLabel }})</th>
                                <th>Aktivitas</th>
                                <th>Produk</th>
                                <th>Pembeli</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($events as $event)
                            <tr>
                                <td data-label="Waktu ({{ $tzLabel }})">
                                    <span class="admin-cell-nowrap">{{ $event->occurred_at?->setTimezone($tz)->format('d M Y H:i') }}</span>
                                </td>
                                <td data-label="Aktivitas">
                                    <span class="analytics-event-badge {{ $event->badgeClass() }}">{{ $event->typeLabel() }}</span>
                                </td>
                                <td data-label="Produk">
                                    <span class="admin-cell-primary">{{ $event->product?->name ?? '—' }}</span>
                                    @if ($event->product?->category)
                                        <div class="admin-cell-sub">{{ $event->product->category->name }}</div>
                                    @endif
                                </td>
                                <td data-label="Pembeli">{{ $event->user?->name ?? '—' }}</td>
                                <td data-label="Keterangan">
                                    @if ($event->order)
                                        <a class="admin-cell-link" href="{{ route('admin.pesanan.show', $event->order_id) }}">{{ $event->order->order_number }}</a>
                                    @endif
                                    @if ($event->reason === 'expired')
                                        <span class="admin-cell-sub">Kedaluwarsa otomatis</span>
                                    @elseif ($event->reason === 'buyer')
                                        <span class="admin-cell-sub">Dibatalkan pembeli</span>
                                    @elseif (! $event->order)
                                        <span class="admin-cell-sub">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $events->links() }}</div>
            @endif
        </section>
    </div>
@endsection
