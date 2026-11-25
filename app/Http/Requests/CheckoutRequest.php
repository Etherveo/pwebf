<?php

namespace App\Http\Requests;

use App\Models\Address;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// ORD-01/ORD-02: payment method, idempotency checkout_key, and fulfillment choice:
// - 'pickup' (default): requires pickup_date + pickup_time in the future (WITA).
// - 'delivery': requires courier_code from config('store.couriers') and either a
//   saved address_id owned by the buyer or recipient + phone + address fields.
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function isDelivery(): bool
    {
        return $this->input('fulfillment_method', 'pickup') === 'delivery';
    }

    public function rules(): array
    {
        $active = array_keys(array_filter(
            config('payment.methods', []),
            fn (array $method) => ($method['active'] ?? false) === true,
        ));

        $couriers = array_keys(config('store.couriers', []));

        $rules = [
            'payment_method' => ['required', 'string', Rule::in($active)],
            'checkout_key' => ['nullable', 'string', 'max:64'],
            'fulfillment_method' => ['sometimes', 'string', Rule::in(['pickup', 'delivery'])],
            'buyer_note' => ['nullable', 'string', 'max:500'],
        ];

        if ($this->isDelivery()) {
            $rules['courier_code'] = ['required', 'string', Rule::in($couriers)];
            $rules['address_id'] = ['nullable', 'integer'];

            if (! $this->filled('address_id')) {
                $rules['shipping_recipient_name'] = ['required', 'string', 'min:2', 'max:150'];
                $rules['shipping_phone'] = ['required', 'string', 'min:8', 'max:30'];
                $rules['shipping_address'] = ['required', 'string', 'min:10', 'max:1000'];
            } else {
                $rules['shipping_recipient_name'] = ['nullable', 'string', 'max:150'];
                $rules['shipping_phone'] = ['nullable', 'string', 'max:30'];
                $rules['shipping_address'] = ['nullable', 'string', 'max:1000'];
            }
        } else {
            $rules['pickup_date'] = ['required', 'date', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && $value < Carbon::now($this->reportingTimezone())->toDateString()) {
                    $fail('Tanggal pengambilan tidak boleh lewat.');
                }
            }];
            $rules['pickup_time'] = ['required', 'date_format:H:i'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->isDelivery()) {
                if ($this->filled('address_id')) {
                    $user = $this->user('web');
                    $ownsAddress = $user !== null && Address::query()
                        ->where('user_id', $user->id)
                        ->where('id', (int) $this->input('address_id'))
                        ->exists();

                    if (! $ownsAddress) {
                        $validator->errors()->add('address_id', 'Alamat pengiriman tidak ditemukan.');
                    }
                }

                return;
            }

            $date = $this->input('pickup_date');
            $time = $this->input('pickup_time');

            if (! is_string($date) || ! is_string($time)) {
                return;
            }

            try {
                $at = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time, $this->reportingTimezone());
            } catch (\Exception) {
                return;
            }

            if ($at->lessThanOrEqualTo(Carbon::now($this->reportingTimezone()))) {
                $validator->errors()->add('pickup_time', 'Jadwal pengambilan harus di masa depan.');
            }
        });
    }

    // Combined pickup datetime in UTC (null when delivery is selected).
    public function pickupAt(): ?Carbon
    {
        if ($this->isDelivery()) {
            return null;
        }

        return Carbon::createFromFormat(
            'Y-m-d H:i',
            $this->validated('pickup_date').' '.$this->validated('pickup_time'),
            $this->reportingTimezone(),
        )->setTimezone('UTC');
    }

    public function fulfillmentPayload(): array
    {
        $validated = $this->validated();

        return [
            'fulfillment_method' => $this->isDelivery() ? 'delivery' : 'pickup',
            'courier_code' => $validated['courier_code'] ?? null,
            'address_id' => isset($validated['address_id']) ? (int) $validated['address_id'] : null,
            'shipping_recipient_name' => $validated['shipping_recipient_name'] ?? null,
            'shipping_phone' => $validated['shipping_phone'] ?? null,
            'shipping_address' => $validated['shipping_address'] ?? null,
            'buyer_note' => isset($validated['buyer_note']) && trim((string) $validated['buyer_note']) !== ''
                ? trim((string) $validated['buyer_note'])
                : null,
        ];
    }

    private function reportingTimezone(): string
    {
        return (string) config('store.reporting_timezone', 'Asia/Makassar');
    }
}
