<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderExpiryService;
use App\Services\OrderStateMachine;
use App\Services\PaymentStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// M3a ORD-10..13. Admin order list, detail, fulfillment status transition, and CSV export.
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly OrderExpiryService $expiry,
        private readonly PaymentStateMachine $payments,
    ) {}

    public function index(Request $request): View
    {
        $this->expiry->expireOverdue();

        $orders = $this->filteredOrdersQuery($request)
            ->paginate(20)
            ->withQueryString();

        return view('admin.pesanan-index', ['orders' => $orders]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->expiry->expireOverdue();

        $orders = $this->filteredOrdersQuery($request)->get();
        $tz = (string) config('store.reporting_timezone', 'Asia/Makassar');
        $filename = 'pesanan-'.strtolower((string) config('store.order_prefix', 'cl')).'-'.now($tz)->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($orders, $tz) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Nomor Pesanan',
                'Tanggal (WITA)',
                'Pembeli',
                'Email',
                'Status',
                'Pemenuhan',
                'Ekspedisi',
                'Nomor Resi',
                'Subtotal (Rp)',
                'Ongkir (Rp)',
                'Total (Rp)',
            ]);

            foreach ($orders as $order) {
                $subtotal = $order->subtotal_idr ?? max(0, (int) $order->total_idr - (int) $order->shipping_cost_idr);
                fputcsv($out, [
                    $order->order_number,
                    $order->created_at?->setTimezone($tz)->format('Y-m-d H:i'),
                    $order->user?->name ?? '—',
                    $order->user?->email ?? '—',
                    $order->statusLabel(),
                    $order->fulfillmentLabel(),
                    $order->courier_name ?? '—',
                    $order->tracking_number ?? '—',
                    (int) $subtotal,
                    (int) $order->shipping_cost_idr,
                    (int) $order->total_idr,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function show(int $order): View
    {
        $found = Order::query()->findOrFail($order);
        $this->expiry->expireIfOverdue($found);

        $found->load(['items.product', 'histories', 'payments.transitions', 'user']);

        return view('admin.pesanan-detail', ['order' => $found->fresh(['items.product', 'histories', 'payments.transitions'])]);
    }

    // ORD-12: only dibayar -> diproses and diproses -> selesai.
    public function updateStatus(AdminOrderStatusRequest $request, int $order): RedirectResponse
    {
        $found = Order::query()->findOrFail($order);
        $admin = Auth::guard('web')->user();

        if ($found->isDelivery() && $request->filled('tracking_number')) {
            $found->update([
                'tracking_number' => trim((string) $request->validated('tracking_number')),
            ]);
        }

        $updated = $this->stateMachine->transition(
            $found,
            (string) $request->validated('to_status'),
            'admin',
            'admin_toko',
            $admin->id,
        );

        return redirect()->route('admin.pesanan.show', $updated)
            ->with('status', 'Pesanan '.$updated->order_number.' diubah ke '.$updated->statusLabel().'.');
    }

    public function confirmPayment(int $order): RedirectResponse
    {
        $found = Order::query()->with('payments')->findOrFail($order);
        $this->expiry->expireIfOverdue($found);

        $pending = $found->fresh(['payments'])->pendingPayment();
        if ($pending === null) {
            return redirect()->route('admin.pesanan.show', $found)
                ->withErrors(['status' => 'Tidak ada pembayaran yang menunggu untuk pesanan ini.']);
        }

        $this->payments->markPaid($pending, 'konfirmasi_admin');

        return redirect()->route('admin.pesanan.show', $found)
            ->with('status', 'Pembayaran pesanan '.$found->order_number.' berhasil dikonfirmasi.');
    }

    private function filteredOrdersQuery(Request $request)
    {
        return Order::query()
            ->with('user')
            ->when(in_array($request->input('status'), Order::STATUSES, true),
                fn ($q) => $q->where('status', $request->input('status')))
            ->when($this->isDate($request->input('dari')),
                fn ($q) => $q->whereDate('created_at', '>=', $request->input('dari')))
            ->when($this->isDate($request->input('sampai')),
                fn ($q) => $q->whereDate('created_at', '<=', $request->input('sampai')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $request->input('q')).'%';
                $q->whereRaw("order_number LIKE ? ESCAPE '\\'", [$term]);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function isDate(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
