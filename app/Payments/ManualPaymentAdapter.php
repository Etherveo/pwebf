<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentCallbackHandler;
use App\Services\PaymentStateMachine;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// Adapter pembayaran netral sementara sebelum integrasi ke gateway riil
// (Midtrans / Xendit / Tripay / Duitku). Tidak ada simulasi palsu:
// instruksi dibangun dari kode referensi lokal & masa berlaku pesanan,
// sementara verifikasi callback tetap melalui endpoint webhook bertanda tangan.
class ManualPaymentAdapter implements PaymentAdapter
{
    public function __construct(
        private readonly PaymentCallbackHandler $handler,
        private readonly PaymentStateMachine $stateMachine,
    ) {}

    public function createPayment(Order $order, string $method, Payment $payment): PaymentInstruction
    {
        return new PaymentInstruction(
            paymentId: $payment->id,
            referenceCode: $payment->reference_code,
            qr: null,
            vaNumber: ctype_digit($payment->reference_code) ? $payment->reference_code : null,
            paymentToken: $payment->payment_token,
            expiresAt: $payment->expires_at->toImmutable(),
        );
    }

    public function getPaymentStatus(string $paymentId): PaymentStatus
    {
        $payment = Payment::find($paymentId);

        if ($payment === null) {
            throw new ModelNotFoundException('Pembayaran tidak ditemukan: '.$paymentId);
        }

        return new PaymentStatus(
            $payment->status,
            $payment->paid_at?->toImmutable(),
            $payment->reference_code,
        );
    }

    public function handleCallback(array $payload, ?string $signature, string $rawBody): CallbackResult
    {
        $requestId = (string) Str::uuid();
        $secret = (string) config('payment.webhook_secret', '');

        if (! SignatureVerifier::verify($rawBody, $signature, $secret)) {
            Log::warning('Webhook pembayaran: signature tidak valid.', [
                'payment_id' => $payload['payment_id'] ?? null,
                'request_id' => $requestId,
            ]);

            return new CallbackResult('unauthorized', null, null, 'Signature tidak valid.', $requestId);
        }

        $status = $payload['status'] ?? null;
        if (! in_array($status, ['paid', 'failed', 'expired'], true)
            || empty($payload['payment_id']) || ! is_string($payload['payment_id'])
            || empty($payload['order_id']) || ! is_string($payload['order_id'])
            || empty($payload['reference']) || ! is_string($payload['reference'])
        ) {
            return new CallbackResult('invalid', null, null, 'Payload tidak lengkap atau status tidak dikenal.', $requestId);
        }

        if ($status === 'paid') {
            $paidAt = $payload['paid_at'] ?? null;
            if (! is_string($paidAt) || strtotime($paidAt) === false) {
                return new CallbackResult('invalid', null, null, 'paid_at wajib dan harus tanggal valid bila status paid.', $requestId);
            }
        }

        $payment = Payment::find($payload['payment_id']);

        if ($payment === null) {
            return new CallbackResult('not_found', null, null, 'Pembayaran tidak ditemukan.', $requestId);
        }

        $payment->loadMissing('order');

        if ($payload['order_id'] !== $payment->order->order_number
            || $payload['reference'] !== $payment->reference_code
        ) {
            return new CallbackResult('invalid', $payment, $payment->order->status, 'order_id atau reference tidak cocok dengan pembayaran.', $requestId);
        }

        if (isset($payload['amount']) && (int) $payload['amount'] !== (int) $payment->amount_idr) {
            return new CallbackResult('invalid', $payment, $payment->order->status, 'Nominal tidak boleh berasal dari payload callback.', $requestId);
        }

        if ($payment->isFinal()) {
            if ($payment->status === $status) {
                return new CallbackResult('already_processed', $payment->fresh(), $payment->order->status, 'Callback sudah diproses sebelumnya.', $requestId);
            }

            return new CallbackResult('conflict', $payment->fresh(), $payment->order->status, 'Pembayaran sudah final dengan status berbeda.', $requestId);
        }

        return $this->handler->handle($payment, $status, $requestId);
    }

    public function cancelPayment(string $paymentId): void
    {
        $payment = Payment::findOrFail($paymentId);

        $this->stateMachine->transition($payment, 'cancelled', 'pembeli_batal');
    }
}
