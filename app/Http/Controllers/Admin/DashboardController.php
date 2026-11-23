<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderExpiryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// Ringkasan penjualan ringan berbasis query SQL langsung (pengganti servis Python analytics).
class DashboardController extends Controller
{
    public function __construct(
        private readonly OrderExpiryService $expiry,
    ) {}

    public function index(): View
    {
        $this->expiry->expireOverdue();

        $tz = (string) config('store.reporting_timezone', 'Asia/Makassar');
        $endWita = Carbon::now($tz)->endOfDay();
        $startWita = $endWita->copy()->subDays(29)->startOfDay();
        $startUtc = $startWita->copy()->setTimezone('UTC');
        $endUtc = $endWita->copy()->setTimezone('UTC');

        $paidOrdersQuery = Order::query()
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startUtc, $endUtc]);

        $revenue30d = (int) (clone $paidOrdersQuery)->sum('total_idr');
        $paidOrders30d = (int) (clone $paidOrdersQuery)->count();

        $unitsSold30d = (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$startUtc, $endUtc])
            ->sum('order_items.quantity');

        $pendingOrders = (int) Order::query()->where('status', 'menunggu_pembayaran')->count();
        $actionableOrders = (int) Order::query()->whereIn('status', ['dibayar', 'diproses'])->count();

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$startUtc, $endUtc])
            ->groupBy('products.id', 'products.name')
            ->select('products.id', 'products.name')
            ->selectRaw('SUM(order_items.quantity) as units_sold, SUM(order_items.subtotal_idr) as revenue_idr')
            ->orderByDesc('units_sold')
            ->orderByDesc('revenue_idr')
            ->limit(5)
            ->get();

        $recentOrders = Order::query()
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'startWita' => $startWita,
            'endWita' => $endWita,
            'revenue30d' => $revenue30d,
            'paidOrders30d' => $paidOrders30d,
            'unitsSold30d' => $unitsSold30d,
            'pendingOrders' => $pendingOrders,
            'actionableOrders' => $actionableOrders,
            'topProducts' => $topProducts,
            'recentOrders' => $recentOrders,
        ]);
    }
}
