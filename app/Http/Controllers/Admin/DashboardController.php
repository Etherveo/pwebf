<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderExpiryService;
use App\Services\StoreMetricsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Ringkasan penjualan & kategori berbasis SQL native serta grafik Chart.js.
class DashboardController extends Controller
{
    public function __construct(
        private readonly OrderExpiryService $expiry,
        private readonly StoreMetricsService $metrics,
    ) {}

    public function index(Request $request): View
    {
        $this->expiry->expireOverdue();

        $period = $this->metrics->resolvePeriod($request, '30d');
        $grafik = in_array($request->query('grafik'), ['omzet', 'unit', 'pesanan'], true)
            ? (string) $request->query('grafik')
            : 'omzet';

        $summary = $this->metrics->dashboardSalesSummary($period, $grafik);

        $recentOrders = Order::query()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(5)
            ->withQueryString();

        $filterParams = array_filter([
            'rentang' => $period['preset'] !== 'custom' ? $period['preset'] : null,
            'dari' => $period['preset'] === 'custom' ? $period['dari'] : null,
            'sampai' => $period['preset'] === 'custom' ? $period['sampai'] : null,
            'grafik' => $grafik !== 'omzet' ? $grafik : null,
        ], static fn ($v) => $v !== null && $v !== '');

        return view('admin.dashboard', [
            'period' => $period,
            'grafik' => $grafik,
            'filterParams' => $filterParams,
            'metrics' => $this->metrics,
            'revenue' => $summary['revenue'],
            'paidOrders' => $summary['paidOrders'],
            'unitsSold' => $summary['unitsSold'],
            'pendingOrders' => $summary['pendingOrders'],
            'actionableOrders' => $summary['actionableOrders'],
            'topProducts' => $summary['topProducts'],
            'categories' => $summary['categories'],
            'salesChart' => $summary['salesChart'],
            'recentOrders' => $recentOrders,
        ]);
    }
}
