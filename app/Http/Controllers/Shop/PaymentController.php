<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\OrderExpiryService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// Buyer payment history, payment detail with status transition timeline,
// payment result pages, and printable receipt (scoped to acting buyer).
class PaymentController extends Controller
{
    public function __construct(
        private readonly OrderExpiryService $expiry,
        private readonly PaymentService $payments,
    ) {}

    // PAY-49: own payments, newest first, paginated, filter by status.
    public function index(Request $request): View
    {
        $user = Auth::guard('web')->user();
        $this->expiry->expireOverdueForUser($user);

        $payments = Payment::query()
            ->where('user_id', $user->id)
            ->when(in_array($request->input('status'), Payment::STATUSES, true),
                fn ($q) => $q->where('status', $request->input('status')))
            ->with('order')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('payments.index', ['payments' => $payments]);
    }

    public function show(string $payment): View
    {
        $found = $this->findOwned($payment);
        $this->expiry->expireIfOverdue($found->order);
        $found = $found->fresh(['order.items', 'transitions']);

        return view('payments.show', [
            'payment' => $found,
            'order' => $found->order,
            'method' => $this->payments->method($found->payment_method),
            'transitions' => $found->transitions->sortBy('occurred_at')->values(),
        ]);
    }

    // PAY-45/46/47: one result page, variant by payment status.
    // Pending is not final: back to the instruction page. Cancelled
    // has no result page in the PRD: back to the order detail.
    public function result(string $payment): View|RedirectResponse
    {
        $found = $this->findOwned($payment);
        $this->expiry->expireIfOverdue($found->order);
        $found = $found->fresh(['order.items']);

        if ($found->status === 'pending') {
            return redirect()->route('pesanan.bayar', $found->order);
        }

        if ($found->status === 'cancelled') {
            return redirect()->route('pesanan.show', $found->order)
                ->with('status', 'Pembayaran dibatalkan bersama pesanannya.');
        }

        return view('payments.hasil-show', [
            'payment' => $found,
            'order' => $found->order,
            'method' => $this->payments->method($found->payment_method),
            'methods' => $this->payments->methods(),
        ]);
    }

    // PAY-48: printable receipt, paid payments only.
    public function receipt(string $payment): View|RedirectResponse
    {
        $found = $this->findOwned($payment);
        $this->expiry->expireIfOverdue($found->order);
        $found = $found->fresh(['order.items']);

        if ($found->status !== 'paid') {
            return redirect()->route('pembayaran.hasil', $found)
                ->with('status', 'Struk hanya tersedia untuk pembayaran yang sudah terbayar.');
        }

        return view('payments.struk-show', [
            'payment' => $found,
            'order' => $found->order,
            'method' => $this->payments->method($found->payment_method),
        ]);
    }

    private function findOwned(string $payment): Payment
    {
        $user = Auth::guard('web')->user();

        return Payment::query()->where('user_id', $user->id)->findOrFail($payment);
    }
}
