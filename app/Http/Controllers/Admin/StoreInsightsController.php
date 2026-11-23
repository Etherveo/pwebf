<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Product;
use App\Services\StoreMetricsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Dua halaman tambahan wawasan admin_toko: Funnel Pembelian dan Aktivitas Produk (+ tab Riwayat).
class StoreInsightsController extends Controller
{
    public function __construct(
        private readonly StoreMetricsService $metrics,
    ) {}

    public function funnel(Request $request): View
    {
        $period = $this->metrics->resolvePeriod($request, '30d');
        $categories = Category::query()->orderBy('name')->get(['id', 'name']);

        $categoryId = $this->resolveCategoryId($request, $categories);
        $sort = array_key_exists((string) $request->query('urut'), StoreMetricsService::FUNNEL_SORTS)
            ? (string) $request->query('urut')
            : 'conversion_desc';
        $search = trim((string) $request->query('q', ''));
        $onlyActive = $request->boolean('aktif');
        $perPage = $this->resolvePerPage($request, [10, 15, 25, 50], 15);

        $funnel = $this->metrics->funnelSummary($period, $categoryId);
        $products = $this->metrics->paginatedProductMetrics(
            period: $period,
            categoryId: $categoryId,
            search: $search,
            sort: $sort,
            onlyActiveEvents: $onlyActive,
            perPage: $perPage,
        );

        $filterParams = $this->cleanParams([
            'rentang' => $period['preset'] !== 'custom' ? $period['preset'] : null,
            'dari' => $period['preset'] === 'custom' ? $period['dari'] : null,
            'sampai' => $period['preset'] === 'custom' ? $period['sampai'] : null,
            'kategori' => $categoryId,
            'urut' => $sort,
            'q' => $search,
            'aktif' => $onlyActive ? '1' : null,
            'per_page' => $perPage !== 15 ? $perPage : null,
        ]);

        return view('admin.funnel', [
            'period' => $period,
            'categories' => $categories,
            'categoryId' => $categoryId,
            'sort' => $sort,
            'search' => $search,
            'onlyActive' => $onlyActive,
            'perPage' => $perPage,
            'funnel' => $funnel,
            'products' => $products,
            'filterParams' => $filterParams,
            'metrics' => $this->metrics,
        ]);
    }

    public function activity(Request $request): View
    {
        $period = $this->metrics->resolvePeriod($request, '30d');
        $categories = Category::query()->orderBy('name')->get(['id', 'name']);

        $categoryId = $this->resolveCategoryId($request, $categories);
        $sort = array_key_exists((string) $request->query('urut'), StoreMetricsService::ACTIVITY_SORTS)
            ? (string) $request->query('urut')
            : 'views_desc';
        $search = trim((string) $request->query('q', ''));
        $onlyActive = $request->boolean('aktif');
        $perPage = $this->resolvePerPage($request, [10, 15, 25, 50], 15);

        $dailyChart = $this->metrics->dailyActivityChart($period, $categoryId);
        $products = $this->metrics->paginatedProductMetrics(
            period: $period,
            categoryId: $categoryId,
            search: $search,
            sort: $sort,
            onlyActiveEvents: $onlyActive,
            perPage: $perPage,
        );

        $filterParams = $this->cleanParams([
            'rentang' => $period['preset'] !== 'custom' ? $period['preset'] : null,
            'dari' => $period['preset'] === 'custom' ? $period['dari'] : null,
            'sampai' => $period['preset'] === 'custom' ? $period['sampai'] : null,
            'kategori' => $categoryId,
            'urut' => $sort,
            'q' => $search,
            'aktif' => $onlyActive ? '1' : null,
            'per_page' => $perPage !== 15 ? $perPage : null,
        ]);

        return view('admin.aktivitas', [
            'period' => $period,
            'categories' => $categories,
            'categoryId' => $categoryId,
            'sort' => $sort,
            'search' => $search,
            'onlyActive' => $onlyActive,
            'perPage' => $perPage,
            'dailyChart' => $dailyChart,
            'products' => $products,
            'filterParams' => $filterParams,
            'metrics' => $this->metrics,
        ]);
    }

    public function history(Request $request): View
    {
        $period = $this->metrics->resolvePeriod($request, '30d');
        $categories = Category::query()->orderBy('name')->get(['id', 'name']);
        $allProducts = Product::query()->orderBy('name')->get(['id', 'name']);

        $categoryId = $this->resolveCategoryId($request, $categories);
        $productId = $request->filled('produk') && $allProducts->contains('id', (int) $request->query('produk'))
            ? (int) $request->query('produk')
            : null;
        $eventType = in_array((string) $request->query('jenis'), Event::TYPES, true)
            ? (string) $request->query('jenis')
            : null;
        $search = trim((string) $request->query('q', ''));
        $perPage = $this->resolvePerPage($request, [15, 20, 50], 20);

        $events = $this->metrics->paginatedEventHistory(
            period: $period,
            eventType: $eventType,
            categoryId: $categoryId,
            productId: $productId,
            search: $search,
            perPage: $perPage,
        );

        $filterParams = $this->cleanParams([
            'rentang' => $period['preset'] !== 'custom' ? $period['preset'] : null,
            'dari' => $period['preset'] === 'custom' ? $period['dari'] : null,
            'sampai' => $period['preset'] === 'custom' ? $period['sampai'] : null,
            'jenis' => $eventType,
            'kategori' => $categoryId,
            'produk' => $productId,
            'q' => $search,
            'per_page' => $perPage !== 20 ? $perPage : null,
        ]);

        return view('admin.aktivitas-riwayat', [
            'period' => $period,
            'categories' => $categories,
            'allProducts' => $allProducts,
            'categoryId' => $categoryId,
            'productId' => $productId,
            'eventType' => $eventType,
            'search' => $search,
            'perPage' => $perPage,
            'events' => $events,
            'filterParams' => $filterParams,
            'metrics' => $this->metrics,
        ]);
    }

    private function resolveCategoryId(Request $request, $categories): ?int
    {
        if (! $request->filled('kategori')) {
            return null;
        }

        $id = (int) $request->query('kategori');

        return $categories->contains('id', $id) ? $id : null;
    }

    /**
     * @param  array<int, int>  $allowed
     */
    private function resolvePerPage(Request $request, array $allowed, int $default): int
    {
        $val = (int) $request->query('per_page', $default);

        return in_array($val, $allowed, true) ? $val : $default;
    }

    private function cleanParams(array $params): array
    {
        return array_filter(
            $params,
            static fn ($v) => $v !== null && $v !== '',
        );
    }
}
