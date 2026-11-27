<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaymentAdapter;
use App\Payments\PaymentInstruction;
use App\Payments\QrPayload;
use Illuminate\Validation\ValidationException;

// M3a/M3b PAY-01/PAY-02/PAY-03/PAY-05/PAY-10. Creates the single
// pending payment for an order and builds its instruction through
// the active PaymentAdapter. The payment_token is 32 CSPRNG bytes,
// URL-safe base64 (43 chars), unique, bound to this one payment.
// reference_code: QR methods get REF- + 10 chars; VA methods get
// a 16-digit reference number. qr_url is filled from the instruction
// when provided by the adapter.
class PaymentService
{
    public function __construct(
        private readonly PaymentStateMachine $stateMachine,
        private readonly PaymentAdapter $adapter,
    ) {}

    // PAY-01: the active method catalog, from config/payment.php.
    public function methods(): array
    {
        $methods = config('payment.methods', []);

        return array_values(array_filter(
            $methods,
            fn (array $method) => ($method['active'] ?? false) === true,
        ));
    }

    public function method(string $code): ?array
    {
        $methods = config('payment.methods', []);

        return $methods[$code] ?? null;
    }

    public function isActiveMethod(string $code): bool
    {
        $method = $this->method($code);

        return $method !== null && ($method['active'] ?? false) === true;
    }

    // PAY-03: one pending payment for a menunggu_pembayaran order.
    // Amount is the server-computed order total; expires_at equals the
    // order's payment_deadline_at (ORD-05 / PAY-04). The caller
    // (CheckoutService) owns the surrounding transaction.
    public function createForOrder(Order $order, string $methodCode): Payment
    {
        $method = $this->method($methodCode);

        if ($method === null || ($method['active'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'payment_method' => 'Metode pembayaran tidak dikenal atau tidak aktif.',
            ]);
        }

        if ($order->status !== 'menunggu_pembayaran') {
            throw ValidationException::withMessages([
                'payment_method' => 'Pembayaran hanya dapat dibuat untuk pesanan yang menunggu pembayaran.',
            ]);
        }

        if (now()->greaterThanOrEqualTo($order->payment_deadline_at)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Batas waktu bayar pesanan sudah terlewati.',
            ]);
        }

        // PAY-63: at most one pending payment per order (also enforced
        // by the partial unique index). A repay first checks this in
        // the controller; this is the backstop.
        if ($order->payments()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages([
                'payment_method' => 'Sudah ada pembayaran yang menunggu untuk pesanan ini.',
            ]);
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'provider' => (string) config('payment.driver', 'manual'),
            'provider_payment_id' => null,
            'payment_method' => $methodCode,
            'amount_idr' => $order->total_idr,
            'currency' => 'IDR',
            'status' => 'pending',
            'reference_code' => $this->uniqueReference($method),
            'payment_token' => $this->uniqueToken(),
            'qr_url' => null,
            'expires_at' => $order->payment_deadline_at,
            'paid_at' => null,
        ]);

        $this->stateMachine->recordInitial($payment, 'checkout');

        $instruction = $this->adapter->createPayment($order, $methodCode, $payment);

        $payment->update(['qr_url' => $instruction->qr?->value]);

        return $payment->fresh();
    }

    public function instructionFor(Payment $payment): PaymentInstruction
    {
        $method = $this->method($payment->payment_method);
        $kind = $method['kind'] ?? 'qr';

        return new PaymentInstruction(
            paymentId: $payment->id,
            referenceCode: $payment->reference_code,
            qr: $payment->qr_url !== null && $payment->qr_url !== ''
                ? new QrPayload('url', $payment->qr_url)
                : null,
            vaNumber: $kind === 'va' ? $payment->reference_code : null,
            paymentToken: $payment->payment_token,
            expiresAt: $payment->expires_at->toImmutable(),
        );
    }

    private function uniqueToken(): string
    {
        do {
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        } while (Payment::query()->where('payment_token', $token)->exists());

        return $token;
    }

    private function uniqueReference(array $method): string
    {
        do {
            $reference = ($method['kind'] ?? 'qr') === 'va'
                ? $this->vaReference((string) ($method['bank_code'] ?? '00'))
                : 'PAY-'.$this->randomCode(10);
        } while (Payment::query()->where('reference_code', $reference)->exists());

        return $reference;
    }

    // PAY-08: 999 + bank code + 11 random digits = 16 digits.
    private function vaReference(string $bankCode): string
    {
        $digits = '';
        for ($i = 0; $i < 11; $i++) {
            $digits .= (string) random_int(0, 9);
        }

        return '999'.$bankCode.$digits;
    }

    // Unambiguous alphabet (no 0/O, 1/I/L) — same alphabet as order
    // numbers (ORD-03), so neither can be misread.
    private function randomCode(int $length): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
