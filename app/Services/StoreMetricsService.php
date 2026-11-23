<?php

namespace App\Services;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Native SQL aggregation, filtering, Chart.js payload builder, and pagination helpers
// for admin_toko views (all reporting buckets aligned to store.reporting_timezone).
class StoreMetricsService
{
    public const PERIOD_PRESETS = [
        'today' => 'Hari Ini',
        '7d' => '7 Hari',
        '30d' => '30 Hari',
        '90d' => '90 Hari',
        'all' => 'Semua Waktu',
    ];

    public const ACTIVITY_SORTS = [
        'views_desc' => 'Terbanyak Dilihat',
        'cart_desc' => 'Masuk Keranjang',
        'booking_desc' => 'Terbanyak Booking',
        'purchases_desc' => 'Terbanyak Dibeli',
        'units_desc' => 'Unit Terjual',
        'revenue_desc' => 'Omzet Tertinggi',
        'conversion_desc' => 'Konversi Tertinggi',
    ];

    public const FUNNEL_SORTS = [
        'conversion_desc' => 'Konversi Tertinggi',
        'views_desc' => 'Terbanyak Dilihat',
        'purchases_desc' => 'Terbanyak Dibayar',
        'dropoff_desc' => 'Drop-off Tertinggi',
    ];

    public function resolvePeriod(Request $request, string $defaultPreset = '30d'): array
    {
        $tz = (string) config('store.reporting_timezone', 'Asia/Makassar');
        $tzLabel = (string) config('store.timezone_label', 'WITA');
        $nowWita = Carbon::now($tz);

        $rawDari = trim((string) $request->query('dari', ''));
        $rawSampai = trim((string) $request->query('sampai', ''));

        if ($rawDari !== '' || $rawSampai !== '') {
            $parsedStart = $this->parseDate($rawDari, $tz);
            $parsedEnd = $this->parseDate($rawSampai, $tz);

            if ($parsedStart !== null || $parsedEnd !== null) {
                $startWita = ($parsedStart ?? $parsedEnd)->copy()->startOfDay();
                $endWita = ($parsedEnd ?? $parsedStart)->copy()->endOfDay();

                if ($startWita->greaterThan($endWita)) {
                    [$startWita, $endWita] = [$endWita->copy()->startOfDay(), $startWita->copy()->endOfDay()];
                }

                return [
                    'preset' => 'custom',
                    'label' => 'Rentang Khusus',
                    'tz' => $tz,
                    'tz_label' => $tzLabel,
                    'is_all_time' => false,
                    'start_wita' => $startWita,
                    'end_wita' => $endWita,
                    'start_utc' => $startWita->copy()->setTimezone('UTC'),
                    'end_utc' => $endWita->copy()->setTimezone('UTC'),
                    'dari' => $startWita->toDateString(),
                    'sampai' => $endWita->toDateString(),
                ];
            }
        }

        $preset = (string) $request->query('rentang', $defaultPreset);
        if (! array_key_exists($preset, self::PERIOD_PRESETS)) {
            $preset = $defaultPreset;
        }

        if ($preset === 'all') {
            return [
                'preset' => 'all',
                'label' => self::PERIOD_PRESETS['all'],
                'tz' => $tz,
                'tz_label' => $tzLabel,
                'is_all_time' => true,
                'start_wita' => null,
                'end_wita' => $nowWita->copy()->endOfDay(),
                'start_utc' => null,
                'end_utc' => $nowWita->copy()->endOfDay()->setTimezone('UTC'),
                'dari' => '',
                'sampai' => '',
            ];
        }

        $daysBack = match ($preset) {
            'today' => 0,
            '7d' => 6,
            '90d' => 89,
            default => 29,
        };

        $endWita = $nowWita->copy()->endOfDay();
        $startWita = $endWita->copy()->subDays($daysBack)->startOfDay();

        return [
            'preset' => $preset,
            'label' => self::PERIOD_PRESETS[$preset],
            'tz' => $tz,
            'tz_label' => $tzLabel,
            'is_all_time' => false,
            'start_wita' => $startWita,
            'end_wita' => $endWita,
            'start_utc' => $startWita->copy()->setTimezone('UTC'),
            'end_utc' => $endWita->copy()->setTimezone('UTC'),
            'dari' => $startWita->toDateString(),
            'sampai' => $endWita->toDateString(),
        ];
    }

    public function dashboardSalesSummary(array $period, string $chartMetric = 'omzet'): array
    {
        $startUtc = $period['start_utc'];
        $endUtc = $period['end_utc'];

        $paidOrdersQuery = DB::table('orders')->whereNotNull('paid_at');
        $this->applyTimestampBounds($paidOrdersQuery, 'paid_at', $startUtc, $endUtc);

        $revenue = (int) (clone $paidOrdersQuery)->sum('total_idr');
        $paidOrders = (int) (clone $paidOrdersQuery)->count();

        $unitsQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('orders.paid_at');
        $this->applyTimestampBounds($unitsQuery, 'orders.paid_at', $startUtc, $endUtc);
        $unitsSold = (int) $unitsQuery->sum('order_items.quantity');

        $pendingOrders = (int) DB::table('orders')->where('status', 'menunggu_pembayaran')->count();
        $actionableOrders = (int) DB::table('orders')->whereIn('status', ['dibayar', 'diproses'])->count();

        $topProductsQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereNotNull('orders.paid_at');
        $this->applyTimestampBounds($topProductsQuery, 'orders.paid_at', $startUtc, $endUtc);

        $topProducts = $topProductsQuery
            ->groupBy('products.id', 'products.name')
            ->select('products.id', 'products.name')
            ->selectRaw('SUM(order_items.quantity) as units_sold, SUM(order_items.subtotal_idr) as revenue_idr')
            ->orderByDesc('units_sold')
            ->orderByDesc('revenue_idr')
            ->limit(5)
            ->get();

        $categories = $this->categorySummary($startUtc, $endUtc);
        $salesChart = $this->buildDashboardSalesChart($period, $chartMetric);

        return [
            'revenue' => $revenue,
            'paidOrders' => $paidOrders,
            'unitsSold' => $unitsSold,
            'pendingOrders' => $pendingOrders,
            'actionableOrders' => $actionableOrders,
            'topProducts' => $topProducts,
            'categories' => $categories,
            'salesChart' => $salesChart,
        ];
    }

    public function categorySummary(?Carbon $startUtc, ?Carbon $endUtc): Collection
    {
        $salesByCat = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereNotNull('orders.paid_at');
        $this->applyTimestampBounds($salesByCat, 'orders.paid_at', $startUtc, $endUtc);

        $salesByCat
            ->groupBy('products.category_id')
            ->select('products.category_id')
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as units_sold')
            ->selectRaw('COALESCE(SUM(order_items.subtotal_idr), 0) as revenue_idr')
            ->selectRaw('COUNT(DISTINCT orders.id) as paid_orders');

        return DB::table('categories')
            ->leftJoinSub($salesByCat, 'sc', 'sc.category_id', '=', 'categories.id')
            ->select('categories.id', 'categories.name')
            ->selectRaw('COALESCE(sc.units_sold, 0) as units_sold')
            ->selectRaw('COALESCE(sc.revenue_idr, 0) as revenue_idr')
            ->selectRaw('COALESCE(sc.paid_orders, 0) as paid_orders')
            ->orderByDesc('revenue_idr')
            ->orderByDesc('units_sold')
            ->orderBy('categories.name')
            ->get();
    }

    public function funnelSummary(array $period, ?int $categoryId = null): array
    {
        $query = DB::table('events')
            ->join('products', 'products.id', '=', 'events.product_id');

        $this->applyTimestampBounds($query, 'events.occurred_at', $period['start_utc'], $period['end_utc']);

        if ($categoryId !== null) {
            $query->where('products.category_id', $categoryId);
        }

        $counts = $query
            ->selectRaw("COUNT(CASE WHEN events.event_type = 'view' THEN 1 END) as views")
            ->selectRaw("COUNT(CASE WHEN events.event_type = 'add_to_cart' THEN 1 END) as add_to_carts")
            ->selectRaw("COUNT(CASE WHEN events.event_type = 'booking' THEN 1 END) as bookings")
            ->selectRaw("COUNT(CASE WHEN events.event_type = 'purchase' THEN 1 END) as purchases")
            ->selectRaw("COUNT(CASE WHEN events.event_type = 'cancel' THEN 1 END) as cancels")
            ->first();

        $views = (int) ($counts->views ?? 0);
        $carts = (int) ($counts->add_to_carts ?? 0);
        $bookings = (int) ($counts->bookings ?? 0);
        $purchases = (int) ($counts->purchases ?? 0);
        $cancels = (int) ($counts->cancels ?? 0);

        $pct = static fn (int $part, int $whole): float => $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;

        $chart = [
            'type' => 'bar',
            'data' => [
                'labels' => ['Dilihat', 'Masuk Keranjang', 'Booking', 'Dibayar'],
                'datasets' => [
                    [
                        'label' => 'Jumlah Aktivitas',
                        'data' => [$views, $carts, $bookings, $purchases],
                        'backgroundColor' => ['#111827', '#374151', '#6B7280', '#15803D'],
                        'borderRadius' => 6,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'indexAxis' => 'y',
                'plugins' => [
                    'legend' => ['display' => false],
                ],
                'scales' => [
                    'x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                ],
            ],
        ];

        return [
            'views' => $views,
            'add_to_carts' => $carts,
            'bookings' => $bookings,
            'purchases' => $purchases,
            'cancels' => $cancels,
            'view_to_cart_pct' => $pct($carts, $views),
            'cart_to_booking_pct' => $pct($bookings, $carts),
            'booking_to_purchase_pct' => $pct($purchases, $bookings),
            'overall_conversion_pct' => $pct($purchases, $views),
            'chart' => $chart,
        ];
    }

    public function dailyActivityChart(array $period, ?int $categoryId = null): array
    {
        [$chartStartWita, $chartEndWita] = $this->boundedDailyRange($period);
        $dates = $this->dateAxis($chartStartWita, $chartEndWita);
        $tz = $period['tz'];

        $buckets = [];
        foreach ($dates as $date) {
            $buckets[$date] = ['view' => 0, 'add_to_cart' => 0, 'purchase' => 0];
        }

        $rows = DB::table('events')
            ->join('products', 'products.id', '=', 'events.product_id')
            ->whereIn('events.event_type', ['view', 'add_to_cart', 'purchase'])
            ->whereBetween('events.occurred_at', [
                $chartStartWita->copy()->setTimezone('UTC'),
                $chartEndWita->copy()->setTimezone('UTC'),
            ])
            ->when($categoryId !== null, fn ($q) => $q->where('products.category_id', $categoryId))
            ->select('events.event_type', 'events.occurred_at')
            ->get();

        foreach ($rows as $row) {
            $date = Carbon::parse($row->occurred_at, 'UTC')->setTimezone($tz)->toDateString();
            if (isset($buckets[$date][$row->event_type])) {
                $buckets[$date][$row->event_type]++;
            }
        }

        $labels = [];
        $viewSeries = [];
        $cartSeries = [];
        $purchaseSeries = [];

        foreach ($dates as $date) {
            $labels[] = Carbon::parse($date, $tz)->format('d M');
            $viewSeries[] = $buckets[$date]['view'];
            $cartSeries[] = $buckets[$date]['add_to_cart'];
            $purchaseSeries[] = $buckets[$date]['purchase'];
        }

        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Dilihat',
                        'data' => $viewSeries,
                        'borderColor' => '#111827',
                        'fill' => true,
                        'tension' => 0.25,
                    ],
                    [
                        'label' => 'Masuk Keranjang',
                        'data' => $cartSeries,
                        'borderColor' => '#6B7280',
                        'fill' => false,
                        'tension' => 0.25,
                    ],
                    [
                        'label' => 'Dibayar',
                        'data' => $purchaseSeries,
                        'borderColor' => '#15803D',
                        'fill' => false,
                        'tension' => 0.25,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'legend' => ['display' => true, 'position' => 'top'],
                ],
                'scales' => [
                    'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                ],
            ],
        ];
    }

    public function paginatedProductMetrics(
        array $period,
        ?int $categoryId = null,
        string $search = '',
        string $sort = 'views_desc',
        bool $onlyActiveEvents = false,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $startUtc = $period['start_utc'];
        $endUtc = $period['end_utc'];

        $eventsAgg = DB::table('events');
        $this->applyTimestampBounds($eventsAgg, 'occurred_at', $startUtc, $endUtc);
        $eventsAgg
            ->groupBy('product_id')
            ->select('product_id')
            ->selectRaw("COUNT(CASE WHEN event_type = 'view' THEN 1 END) as views_count")
            ->selectRaw("COUNT(CASE WHEN event_type = 'add_to_cart' THEN 1 END) as cart_count")
            ->selectRaw("COUNT(CASE WHEN event_type = 'booking' THEN 1 END) as booking_count")
            ->selectRaw("COUNT(CASE WHEN event_type = 'purchase' THEN 1 END) as purchase_events_count")
            ->selectRaw("COUNT(CASE WHEN event_type = 'cancel' THEN 1 END) as cancel_count");

        $salesAgg = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('orders.paid_at');
        $this->applyTimestampBounds($salesAgg, 'orders.paid_at', $startUtc, $endUtc);
        $salesAgg
            ->groupBy('order_items.product_id')
            ->select('order_items.product_id')
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as units_sold')
            ->selectRaw('COALESCE(SUM(order_items.subtotal_idr), 0) as revenue_idr');

        $query = DB::table('products')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoinSub($eventsAgg, 'ev', 'ev.product_id', '=', 'products.id')
            ->leftJoinSub($salesAgg, 'sl', 'sl.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                'products.is_active',
                'products.category_id',
                'categories.name as category_name',
            )
            ->selectRaw('COALESCE(ev.views_count, 0) as views_count')
            ->selectRaw('COALESCE(ev.cart_count, 0) as cart_count')
            ->selectRaw('COALESCE(ev.booking_count, 0) as booking_count')
            ->selectRaw('COALESCE(ev.purchase_events_count, 0) as purchase_events_count')
            ->selectRaw('COALESCE(ev.cancel_count, 0) as cancel_count')
            ->selectRaw('COALESCE(sl.units_sold, 0) as units_sold')
            ->selectRaw('COALESCE(sl.revenue_idr, 0) as revenue_idr')
            ->selectRaw('CASE WHEN COALESCE(ev.views_count, 0) > 0 THEN ROUND((COALESCE(ev.purchase_events_count, 0) * 100.0) / ev.views_count, 1) ELSE 0.0 END as conversion_rate')
            ->selectRaw('(COALESCE(ev.views_count, 0) - COALESCE(ev.purchase_events_count, 0)) as drop_off_count');

        if ($categoryId !== null) {
            $query->where('products.category_id', $categoryId);
        }

        $trimmedSearch = trim($search);
        if ($trimmedSearch !== '') {
            $term = $this->escapedLikeTerm($trimmedSearch);
            $query->whereRaw("lower(products.name) LIKE ? ESCAPE '\\'", [$term]);
        }

        if ($onlyActiveEvents) {
            $query->whereRaw('(COALESCE(ev.views_count, 0) + COALESCE(ev.cart_count, 0) + COALESCE(ev.booking_count, 0) + COALESCE(ev.purchase_events_count, 0) + COALESCE(ev.cancel_count, 0) + COALESCE(sl.units_sold, 0)) > 0');
        }

        match ($sort) {
            'cart_desc' => $query->orderByDesc('cart_count')->orderByDesc('views_count'),
            'booking_desc' => $query->orderByDesc('booking_count')->orderByDesc('views_count'),
            'purchases_desc' => $query->orderByDesc('purchase_events_count')->orderByDesc('units_sold'),
            'units_desc' => $query->orderByDesc('units_sold')->orderByDesc('revenue_idr'),
            'revenue_desc' => $query->orderByDesc('revenue_idr')->orderByDesc('units_sold'),
            'conversion_desc' => $query->orderByDesc('conversion_rate')->orderByDesc('purchase_events_count'),
            'dropoff_desc' => $query->orderByDesc('drop_off_count')->orderByDesc('views_count'),
            default => $query->orderByDesc('views_count')->orderByDesc('cart_count'),
        };

        $query->orderBy('products.name');

        return $query->paginate($perPage)->withQueryString();
    }

    public function paginatedEventHistory(
        array $period,
        ?string $eventType = null,
        ?int $categoryId = null,
        ?int $productId = null,
        string $search = '',
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Event::query()
            ->with([
                'product:id,category_id,name',
                'product.category:id,name',
                'user:id,name',
                'order:id,order_number',
            ]);

        $this->applyTimestampBounds($query, 'occurred_at', $period['start_utc'], $period['end_utc']);

        if ($eventType !== null && in_array($eventType, Event::TYPES, true)) {
            $query->where('event_type', $eventType);
        }

        if ($productId !== null) {
            $query->where('product_id', $productId);
        }

        if ($categoryId !== null) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', $categoryId));
        }

        $trimmedSearch = trim($search);
        if ($trimmedSearch !== '') {
            $term = $this->escapedLikeTerm($trimmedSearch);
            $query->where(function ($outer) use ($term) {
                $outer->whereHas('product', fn ($q) => $q->whereRaw("lower(name) LIKE ? ESCAPE '\\'", [$term]))
                    ->orWhereHas('user', fn ($q) => $q->whereRaw("lower(name) LIKE ? ESCAPE '\\'", [$term]));
            });
        }

        return $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function buildFilterUrl(string $routeName, array $currentParams, array $overrides): string
    {
        $merged = array_merge($currentParams, $overrides);
        unset($merged['page']);

        $filtered = array_filter(
            $merged,
            static fn ($val) => $val !== null && $val !== '' && $val !== false
        );

        return route($routeName, $filtered);
    }

    private function buildDashboardSalesChart(array $period, string $chartMetric): array
    {
        [$chartStartWita, $chartEndWita] = $this->boundedDailyRange($period);
        $dates = $this->dateAxis($chartStartWita, $chartEndWita);
        $tz = $period['tz'];

        $buckets = [];
        foreach ($dates as $date) {
            $buckets[$date] = ['omzet' => 0, 'unit' => 0, 'pesanan' => 0];
        }

        $startUtc = $chartStartWita->copy()->setTimezone('UTC');
        $endUtc = $chartEndWita->copy()->setTimezone('UTC');

        $orders = DB::table('orders')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startUtc, $endUtc])
            ->select('id', 'paid_at', 'total_idr')
            ->get();

        foreach ($orders as $order) {
            $date = Carbon::parse($order->paid_at, 'UTC')->setTimezone($tz)->toDateString();
            if (isset($buckets[$date])) {
                $buckets[$date]['omzet'] += (int) $order->total_idr;
                $buckets[$date]['pesanan']++;
            }
        }

        if ($chartMetric === 'unit') {
            $items = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereNotNull('orders.paid_at')
                ->whereBetween('orders.paid_at', [$startUtc, $endUtc])
                ->select('orders.paid_at', 'order_items.quantity')
                ->get();

            foreach ($items as $item) {
                $date = Carbon::parse($item->paid_at, 'UTC')->setTimezone($tz)->toDateString();
                if (isset($buckets[$date])) {
                    $buckets[$date]['unit'] += (int) $item->quantity;
                }
            }
        }

        $metricKey = in_array($chartMetric, ['omzet', 'unit', 'pesanan'], true) ? $chartMetric : 'omzet';
        $seriesLabel = match ($metricKey) {
            'unit' => 'Unit Terjual',
            'pesanan' => 'Pesanan Terbayar',
            default => 'Pendapatan Harian (Rp)',
        };

        $labels = [];
        $values = [];
        foreach ($dates as $date) {
            $labels[] = Carbon::parse($date, $tz)->format('d M');
            $values[] = $buckets[$date][$metricKey];
        }

        return [
            'metric' => $metricKey,
            'is_rupiah' => $metricKey === 'omzet',
            'window_start' => $chartStartWita,
            'window_end' => $chartEndWita,
            'config' => [
                'type' => 'line',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => $seriesLabel,
                            'data' => $values,
                            'borderColor' => '#111827',
                            'fill' => true,
                            'tension' => 0.25,
                        ],
                    ],
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'plugins' => [
                        'legend' => ['display' => false],
                    ],
                    'scales' => [
                        'y' => [
                            'beginAtZero' => true,
                            'ticks' => $metricKey === 'omzet' ? new \stdClass : ['precision' => 0],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function boundedDailyRange(array $period): array
    {
        $tz = $period['tz'];
        $endWita = $period['end_wita']->copy()->endOfDay();

        if ($period['is_all_time'] || $period['start_wita'] === null) {
            $earliestOrder = DB::table('orders')->whereNotNull('paid_at')->min('paid_at');
            $earliestEvent = DB::table('events')->min('occurred_at');

            $candidates = array_filter([$earliestOrder, $earliestEvent]);
            if ($candidates !== []) {
                sort($candidates);
                $startWita = Carbon::parse($candidates[0], 'UTC')->setTimezone($tz)->startOfDay();
            } else {
                $startWita = $endWita->copy()->subDays(29)->startOfDay();
            }
        } else {
            $startWita = $period['start_wita']->copy()->startOfDay();
        }

        // Guard chart bucket allocation to a safe 366-day ceiling.
        if ($startWita->diffInDays($endWita) > 365) {
            $startWita = $endWita->copy()->subDays(365)->startOfDay();
        }

        return [$startWita, $endWita];
    }

    /**
     * @return array<int, string>
     */
    private function dateAxis(Carbon $startWita, Carbon $endWita): array
    {
        $dates = [];
        $cursor = $startWita->copy()->startOfDay();
        $limit = $endWita->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($limit)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    private function applyTimestampBounds(object $query, string $column, ?Carbon $startUtc, ?Carbon $endUtc): void
    {
        if ($startUtc !== null && $endUtc !== null) {
            $query->whereBetween($column, [$startUtc, $endUtc]);
        }
    }

    private function parseDate(string $value, string $tz): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $value, $tz);

            return $parsed !== false && $parsed->format('Y-m-d') === $value ? $parsed : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function escapedLikeTerm(string $keyword): string
    {
        $escaped = str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            mb_strtolower($keyword),
        );

        return '%'.$escaped.'%';
    }
}
