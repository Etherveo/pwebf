@php
    $presets = \App\Services\StoreMetricsService::PERIOD_PRESETS;
    $showCategories = isset($categories) && $categories->isNotEmpty();
    $showSearch = $showSearch ?? false;
    $currentSearch = $search ?? '';
    $preservedHidden = collect($filterParams ?? [])
        ->except(['rentang', 'dari', 'sampai', 'q', 'page'])
        ->all();
@endphp

<section class="card admin-panel analytics-filter" aria-label="Filter data">
    <div class="analytics-filter-top">
        <div class="analytics-quick" role="group" aria-label="Filter rentang waktu cepat">
            @foreach ($presets as $key => $label)
                <a
                    href="{{ $metrics->buildFilterUrl($routeName, $filterParams, ['rentang' => $key, 'dari' => null, 'sampai' => null]) }}"
                    class="btn-secondary analytics-quick-pill"
                    @if ($period['preset'] === $key) aria-current="true" @endif
                >{{ $label }}</a>
            @endforeach
        </div>

        <div class="analytics-period-badges">
            <span class="analytics-period-badge">
                <svg class="analytics-period-icon" aria-hidden="true" viewBox="0 0 20 20" fill="none"><rect x="3" y="4.5" width="14" height="12" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3 8.5H17M7 2.5V5.5M13 2.5V5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                @if ($period['is_all_time'])
                    Semua waktu ({{ $period['tz_label'] }})
                @else
                    {{ $period['start_wita']->format('d M Y') }} &ndash; {{ $period['end_wita']->format('d M Y') }} ({{ $period['tz_label'] }})
                @endif
            </span>
        </div>
    </div>

    @if ($showCategories)
        <div class="analytics-sort-pills" role="group" aria-label="Filter kategori produk">
            <span class="admin-label">Kategori:</span>
            <a
                href="{{ $metrics->buildFilterUrl($routeName, $filterParams, ['kategori' => null]) }}"
                @class(['btn-ghost-sm', 'is-active' => ($categoryId ?? null) === null])
                @if (($categoryId ?? null) === null) aria-current="true" @endif
            >Semua Kategori</a>
            @foreach ($categories as $cat)
                <a
                    href="{{ $metrics->buildFilterUrl($routeName, $filterParams, ['kategori' => $cat->id]) }}"
                    @class(['btn-ghost-sm', 'is-active' => (int) ($categoryId ?? 0) === (int) $cat->id])
                    @if ((int) ($categoryId ?? 0) === (int) $cat->id) aria-current="true" @endif
                >{{ $cat->name }}</a>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route($routeName) }}" class="admin-filters analytics-filter-form">
        @foreach ($preservedHidden as $hiddenKey => $hiddenValue)
            <input type="hidden" name="{{ $hiddenKey }}" value="{{ $hiddenValue }}">
        @endforeach

        @if ($showSearch)
            <div class="admin-filter-group admin-filter-grow">
                <label for="filter-q" class="admin-label">Pencarian</label>
                <input
                    class="input"
                    id="filter-q"
                    name="q"
                    value="{{ $currentSearch }}"
                    placeholder="{{ $searchPlaceholder ?? 'Cari produk...' }}"
                >
            </div>
        @endif

        <div class="admin-filter-group">
            <label for="filter-dari" class="admin-label">Dari tanggal ({{ $period['tz_label'] }})</label>
            <input class="input" type="date" id="filter-dari" name="dari" value="{{ $period['dari'] }}">
        </div>

        <div class="admin-filter-group">
            <label for="filter-sampai" class="admin-label">Sampai tanggal ({{ $period['tz_label'] }})</label>
            <input class="input" type="date" id="filter-sampai" name="sampai" value="{{ $period['sampai'] }}">
        </div>

        <div class="admin-filter-actions">
            <button class="btn-primary" type="submit">Terapkan</button>
            @if (! empty($filterParams))
                <a class="btn-secondary" href="{{ route($routeName) }}">Reset</a>
            @endif
        </div>
    </form>

    {{ $slot ?? '' }}
</section>
